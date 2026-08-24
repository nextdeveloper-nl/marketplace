<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pulls Shopify products and their variants into marketplace_products and
 * marketplace_product_catalogs, keyed through the mapping tables.
 *
 * The join key is the Shopify GID, never the SKU: Shopify does not enforce SKU
 * uniqueness, and matching on it is the documented way catalogues get corrupted.
 * Per-record guards live in ShopifyApplyService so the webhook processor
 * applies products by exactly the same rules.
 */
class SyncShopifyProductsJob extends AbstractShopifySyncJob
{
    protected function entityType(): string
    {
        return 'products';
    }

    protected function sync(ShopifyService $service, Carbon $since): array
    {
        $provider = $service->provider;
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        if (! $this->policyAllowsPull($provider, 'products')) {
            return ['processed' => 0, 'high_watermark' => null, 'details' => ['skipped' => 'sync_policy.products.direction=push']];
        }

        $created = $updated = $skipped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchProducts($since) as $raw) {
            $n = $adapter->normalizeProductData($raw);

            $watermark = $this->maxWatermark($watermark, $n['external_updated_at']);

            $outcome = $applier->applyProduct($n, $this->dryRun);

            match ($outcome) {
                ShopifyApplyService::CREATED => $created++,
                ShopifyApplyService::UPDATED => $updated++,
                default => $skipped++,
            };

            if ($this->dryRun && $outcome !== ShopifyApplyService::SKIPPED && count($samples) < 25) {
                $samples[] = $outcome.': '.$n['name'].' ('.count($n['variants']).' variants)';
            }
        }

        return [
            'processed' => $created + $updated,
            'high_watermark' => $watermark,
            'details' => array_filter([
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
