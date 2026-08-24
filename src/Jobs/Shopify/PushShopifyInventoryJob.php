<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Exceptions\StaleInventoryException;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pushes locally-edited stock back to Shopify, compare-and-swap guarded.
 *
 * Reconciling rather than event-driven on purpose. An observer firing on every
 * quantity write would have to distinguish our own inbound sync writes from a
 * human's edit — the exact distinction that already exists here as
 * "catalogs.updated_at is beyond mapping.last_synced_at". Reusing it means pull
 * and push cannot disagree about what counts as a local edit, and a missed run
 * simply retries next time instead of losing the change.
 *
 * The compare-and-swap is the point. Blind-writing an absolute quantity is how
 * integrations oversell: between reading 10 and writing 10 a customer buys one,
 * and the write silently restores the sold unit. changeFromQuantity makes
 * Shopify reject that, and a rejection is re-read and re-decided rather than
 * retried with the same stale number.
 */
class PushShopifyInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    public int $timeout = 900;

    public int $tries = 1;

    /** @var array<string, mixed> */
    public array $report = [];

    public function __construct(public int $providerId, public bool $dryRun = false) {}

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($this->providerId);

        if (! $provider || ! $provider->is_active) {
            $this->report = ['skipped' => 'provider missing or paused'];

            return;
        }

        if (! $provider->canPush('inventory')) {
            $this->report = ['skipped' => 'sync_policy.inventory.direction does not allow push'];

            return;
        }

        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);
        UserHelper::bypassRolesCheck(true);

        $service = new ShopifyService($provider);
        $adapter = $service->getAdapter();

        $pushed = $inSync = $stale = $failed = $skipped = 0;
        $samples = [];

        $mappings = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->whereNotNull('external_inventory_item_id')
            ->get();

        foreach ($mappings as $mapping) {
            $catalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->find($mapping->marketplace_product_catalog_id);

            if (! $catalog) {
                $skipped++;

                continue;
            }

            // Same definition of "somebody edited this locally" the pull guard
            // uses, so the two halves can never disagree.
            $localEditedAt = $catalog->updated_at ? Carbon::parse($catalog->updated_at) : null;
            $lastSyncedAt = $mapping->last_synced_at ? Carbon::parse($mapping->last_synced_at) : null;

            if ($localEditedAt === null || ($lastSyncedAt !== null && ! $localEditedAt->greaterThan($lastSyncedAt))) {
                $skipped++;

                continue;
            }

            $desired = (int) $catalog->quantity_in_inventory;

            try {
                $remote = $this->readRemote($service, (string) $mapping->external_inventory_item_id);

                if ($remote === null) {
                    $skipped++;

                    continue;
                }

                if ($remote['available'] === $desired) {
                    // Already agrees; record that we have reconciled it so the
                    // row stops looking locally-edited on every future run.
                    if (! $this->dryRun) {
                        $mapping->external_updated_at = $remote['updated_at'];
                        $mapping->last_synced_at = Carbon::now();
                        $mapping->saveQuietly();
                    }

                    $inSync++;

                    continue;
                }

                if ($this->dryRun) {
                    if (count($samples) < 25) {
                        $samples[] = ($catalog->sku ?: $catalog->name).': shopify '.$remote['available'].' -> '.$desired;
                    }
                    $pushed++;

                    continue;
                }

                $adapter->pushInventoryLevel(
                    $catalog,
                    $desired,
                    $remote['available'],
                    // The remote state this decision was based on: a retry of
                    // this same push reuses the key, a later one does not.
                    $remote['updated_at']?->toIso8601String()
                );

                $after = $this->readRemote($service, (string) $mapping->external_inventory_item_id);

                $mapping->external_updated_at = $after['updated_at'] ?? Carbon::now();
                $mapping->last_synced_at = Carbon::now();
                $mapping->saveQuietly();

                $pushed++;
            } catch (StaleInventoryException $e) {
                /*
                 * Somebody sold a unit between our read and our write. Re-read
                 * and re-decide; never blind-retry the same absolute number,
                 * which would restore the sold unit and oversell it again.
                 */
                $stale++;

                $current = $this->readRemote($service, (string) $mapping->external_inventory_item_id);

                Log::warning(__METHOD__.' - inventory changed underneath the push; re-reading instead of forcing', [
                    'provider_id' => $provider->id,
                    'catalog_id' => $catalog->id,
                    'wanted' => $desired,
                    'remote_now' => $current['available'] ?? null,
                ]);

                if ($current !== null && ! $this->dryRun) {
                    // Take Shopify's number as the new truth for this round and
                    // let the operator or the next local edit decide again.
                    $catalog->updateQuietly(['quantity_in_inventory' => $current['available']]);
                    $mapping->external_updated_at = $current['updated_at'];
                    $mapping->last_synced_at = Carbon::now();
                    $mapping->saveQuietly();
                }
            } catch (\Throwable $e) {
                $failed++;

                Log::error(__METHOD__.' - inventory push failed', [
                    'provider_id' => $provider->id,
                    'catalog_id' => $catalog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->report = array_filter([
            'provider_id' => $provider->id,
            'pushed' => $pushed,
            'already_in_sync' => $inSync,
            'stale_reread' => $stale,
            'failed' => $failed,
            'no_local_edit' => $skipped,
            'dry_run' => $this->dryRun,
            'would_push' => $this->dryRun ? $samples : null,
        ], fn ($v) => $v !== null);

        Log::info(__METHOD__.' - inventory push completed', $this->report);
    }

    /**
     * Current remote level at the pinned location.
     *
     * Read immediately before the write so changeFromQuantity is as fresh as it
     * can be; the CAS covers the remaining window.
     *
     * @return array{available: int, updated_at: Carbon|null}|null
     */
    private function readRemote(ShopifyService $service, string $inventoryItemGid): ?array
    {
        $locationId = (string) data_get($service->provider->getApiConfigArray(), 'location_id', '');

        if ($locationId === '') {
            return null;
        }

        $data = $service->getAdapter()->getClient()->query(
            ShopifyGraphQL::INVENTORY_LEVEL_BY_ITEM,
            ['itemId' => $inventoryItemGid, 'locationId' => $locationId]
        );

        $level = data_get($data, 'inventoryItem.inventoryLevel');

        if (! is_array($level)) {
            return null;
        }

        $available = 0;

        foreach (data_get($level, 'quantities', []) as $quantity) {
            if (data_get($quantity, 'name') === 'available') {
                $available = (int) data_get($quantity, 'quantity', 0);
            }
        }

        return [
            'available' => $available,
            'updated_at' => $service->getAdapter()->parseExternalDate(data_get($level, 'updatedAt')),
        ];
    }
}
