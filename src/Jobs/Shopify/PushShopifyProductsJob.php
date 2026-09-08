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
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pushes locally-edited catalogue content back to Shopify.
 *
 * This is the most destructive thing the integration can do, so it is the most
 * heavily fenced. Three gates stand in front of a single write:
 *
 *   1. sync_policy.products.direction must allow push (default is pull-only).
 *   2. api_config.product_push.approved_at must be set, which requires an
 *      operator to have looked at a dry-run diff and approved it.
 *   3. MAX_CHANGE_RATIO — a run that would rewrite most of the catalogue is far
 *      more likely to be a mapping bug than a merchant's intent, so it aborts
 *      and asks for a human instead of proceeding.
 *
 * Only products that came FROM this shop are ever touched; push never creates.
 * After each write the product is re-read and re-applied locally, which resets
 * the fingerprint so the webhook our own write triggers is recognised as ours
 * and does not bounce back as another change.
 */
class PushShopifyProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Refuse to push if more than this share of mapped products would change.
     */
    public const MAX_CHANGE_RATIO = 0.5;

    /**
     * Below this many mappings the ratio guard is meaningless.
     */
    public const SMALL_CATALOGUE = 4;

    public int $timeout = 900;

    public int $tries = 1;

    /** @var array<string, mixed> */
    public array $report = [];

    public function __construct(public int $providerId, public bool $dryRun = false, public bool $force = false) {}

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)->find($this->providerId);

        if (! $provider || ! $provider->is_active) {
            $this->report = ['skipped' => 'provider missing or paused'];

            return;
        }

        if (! $provider->canPush('products')) {
            $this->report = ['skipped' => 'sync_policy.products.direction is pull-only'];

            return;
        }

        $approved = data_get($provider->getApiConfigArray(), 'product_push.approved_at');

        if (! $this->dryRun && $approved === null) {
            $this->report = ['skipped' => 'catalogue push not approved — run --dry-run then --approve'];

            return;
        }

        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);
        UserHelper::bypassRolesCheck(true);

        $service = new ShopifyService($provider);
        $adapter = $service->getAdapter();

        $mappings = ProductMappings::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->get();

        $candidates = [];

        foreach ($mappings as $mapping) {
            $product = Products::withoutGlobalScope(AuthorizationScope::class)
                ->withoutGlobalScope(LimitScope::class)->find($mapping->marketplace_product_id);

            if (! $product) {
                continue;
            }

            // "Somebody edited this here" — the same rule the pull guard uses,
            // so the two halves cannot disagree about what a local edit is.
            $localEditedAt = $product->updated_at ? Carbon::parse($product->updated_at) : null;
            $lastSyncedAt = $mapping->last_synced_at ? Carbon::parse($mapping->last_synced_at) : null;

            if ($localEditedAt === null || ($lastSyncedAt !== null && ! $localEditedAt->greaterThan($lastSyncedAt))) {
                continue;
            }

            $candidates[] = [$product, $mapping];
        }

        $mapped = $mappings->count();
        $ratio = $mapped > 0 ? count($candidates) / $mapped : 0.0;

        if (! $this->force && $mapped > self::SMALL_CATALOGUE && $ratio > self::MAX_CHANGE_RATIO) {
            Log::critical(__METHOD__.' - catalogue push aborted: implausible number of products changed locally', [
                'provider_id' => $provider->id,
                'mapped' => $mapped,
                'would_change' => count($candidates),
            ]);

            $this->report = [
                'provider_id' => $provider->id,
                'aborted' => true,
                'reason' => 'more than '.(int) (self::MAX_CHANGE_RATIO * 100).'% of mapped products changed locally; refusing to rewrite the catalogue (--force overrides)',
                'mapped' => $mapped,
                'would_change' => count($candidates),
            ];

            return;
        }

        $pushed = $unchanged = $failed = 0;
        $diffs = [];

        foreach ($candidates as [$product, $mapping]) {
            try {
                $diff = $adapter->diffProduct($product);

                if ($diff === null) {
                    // Nothing actually differs; record that we reconciled it so
                    // the row stops looking locally-edited on every run.
                    if (! $this->dryRun) {
                        $mapping->last_synced_at = Carbon::now();
                        $mapping->saveQuietly();
                    }

                    $unchanged++;

                    continue;
                }

                if ($this->dryRun) {
                    if (count($diffs) < 50) {
                        $diffs[] = ['product' => $product->name, 'changes' => $diff['changes']];
                    }
                    $pushed++;

                    continue;
                }

                $adapter->pushProduct($product);
                $this->reapply($service, (string) $mapping->external_product_id);
                $this->pushVariants($service, $product);

                $pushed++;
            } catch (\Throwable $e) {
                $failed++;

                Log::error(__METHOD__.' - catalogue push failed', [
                    'provider_id' => $provider->id,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->report = array_filter([
            'provider_id' => $provider->id,
            'pushed' => $pushed,
            'already_matching' => $unchanged,
            'failed' => $failed,
            'mapped' => $mapped,
            'dry_run' => $this->dryRun,
            'diffs' => $this->dryRun ? $diffs : null,
        ], fn ($v) => $v !== null);

        if ($this->dryRun) {
            // Record that a diff was actually reviewed; --approve refuses
            // without a recent one, so nobody can enable push blind.
            $config = $provider->getApiConfigArray();
            $config['product_push']['dry_run_at'] = Carbon::now()->toIso8601String();
            $config['product_push']['dry_run_would_change'] = $pushed;
            $provider->updateQuietly(['api_config' => $config]);
        }

        Log::info(__METHOD__.' - catalogue push completed', array_diff_key($this->report, ['diffs' => 1]));
    }

    /**
     * Re-read what Shopify now holds and apply it, so our fingerprint matches
     * the remote state and the webhook our push just triggered is a no-op.
     */
    private function reapply(ShopifyService $service, string $externalId): void
    {
        $node = data_get(
            $service->getAdapter()->getClient()->query(ShopifyGraphQL::PRODUCT_BY_ID, ['id' => $externalId]),
            'product'
        );

        if (is_array($node)) {
            $service->applier()->applyProduct($service->getAdapter()->normalizeProductData($node));
        }
    }

    /**
     * Push variant prices for a product whose catalogue rows were edited here.
     */
    private function pushVariants(ShopifyService $service, Products $product): void
    {
        $catalogs = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $product->id)
            ->get();

        foreach ($catalogs as $catalog) {
            $mapping = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
                ->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $service->provider->id)
                ->where('marketplace_product_catalog_id', $catalog->id)
                ->first();

            if (! $mapping) {
                continue;
            }

            $remotePrice = (float) data_get($catalog->args, 'shopify.pushed_price', -1);

            if (abs((float) $catalog->price - $remotePrice) < 0.005) {
                continue;
            }

            try {
                $service->getAdapter()->pushCatalog($catalog);
            } catch (\Throwable $e) {
                Log::warning(__METHOD__.' - variant price push failed', [
                    'catalog_id' => $catalog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
