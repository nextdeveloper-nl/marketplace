<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Detects products deleted in Shopify that no delta query can reveal.
 *
 * Shopify has no deleted_at and its delete webhooks carry only an id, so a
 * delete that arrives while we are down is invisible forever afterwards: the
 * product simply stops appearing in updated_at queries, which is
 * indistinguishable from not having changed. Diffing the full remote id set
 * against our mappings is the only way to notice, which is why this runs
 * nightly rather than being left to webhooks.
 *
 * Safety rail: a sweep that suddenly cannot see most of the catalogue is far
 * more likely to be a broken read than a merchant deleting everything, so a
 * diff beyond MAX_DELETE_RATIO aborts and asks for a human instead of
 * cascading the deletions.
 */
class ShopifyDeletionSweepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Refuse to sweep if more than this share of mapped products is missing.
     */
    public const MAX_DELETE_RATIO = 0.34;

    /**
     * Below this many mappings the ratio guard is meaningless, so allow it.
     */
    public const SMALL_CATALOGUE = 5;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @var array<int, array<string, mixed>> */
    public array $report = [];

    public function __construct(public ?int $providerId = null, public bool $dryRun = false)
    {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(): void
    {
        $providers = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw("lower(trim(adapter)) = 'shopify'")
            ->where('is_active', true)
            ->when($this->providerId, fn ($q) => $q->where('id', $this->providerId))
            ->get();

        foreach ($providers as $provider) {
            UserHelper::setUserById($provider->iam_user_id);
            UserHelper::setCurrentAccountById($provider->iam_account_id);
            UserHelper::bypassRolesCheck(true);

            try {
                $this->report[] = ['provider_id' => $provider->id] + $this->sweep($provider);
            } catch (\Throwable $e) {
                $this->report[] = ['provider_id' => $provider->id, 'error' => $e->getMessage()];

                Log::error(__METHOD__.' - deletion sweep failed', [
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sweep(Providers $provider): array
    {
        $service = new ShopifyService($provider);
        $client = $service->getAdapter()->getClient();

        $remoteIds = [];

        foreach ($client->paginate(ShopifyGraphQL::PRODUCT_IDS_PAGE, [], 'products') as $node) {
            $remoteIds[(string) data_get($node, 'id')] = true;
        }

        $mappings = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->get();

        $missing = $mappings
            ->filter(fn ($m) => ! isset($remoteIds[(string) $m->external_product_id]))
            ->values();

        $mapped = $mappings->count();
        $ratio = $mapped > 0 ? $missing->count() / $mapped : 0.0;

        if ($mapped > self::SMALL_CATALOGUE && $ratio > self::MAX_DELETE_RATIO) {
            Log::critical(__METHOD__.' - deletion sweep aborted: implausible number of products missing', [
                'provider_id' => $provider->id,
                'mapped' => $mapped,
                'missing' => $missing->count(),
                'remote_seen' => count($remoteIds),
            ]);

            return [
                'aborted' => true,
                'reason' => 'more than '.(int) (self::MAX_DELETE_RATIO * 100).'% of mapped products missing; refusing to cascade deletes',
                'mapped' => $mapped,
                'missing' => $missing->count(),
            ];
        }

        $deleted = [];

        foreach ($missing as $mapping) {
            if (! $this->dryRun) {
                $service->applier()->deleteProduct((string) $mapping->external_product_id);
            }

            $deleted[] = (string) $mapping->external_product_id;
        }

        if ($deleted !== []) {
            Log::warning(__METHOD__.' - products deleted in Shopify were removed locally', [
                'provider_id' => $provider->id,
                'count' => count($deleted),
                'dry_run' => $this->dryRun,
            ]);
        }

        return [
            'remote_products' => count($remoteIds),
            'mapped' => $mapped,
            'deleted' => count($deleted),
            'dry_run' => $this->dryRun,
            'external_ids' => array_slice($deleted, 0, 25),
        ];
    }
}
