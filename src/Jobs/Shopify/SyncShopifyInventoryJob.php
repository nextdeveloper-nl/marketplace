<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pulls inventory levels for the provider's pinned location into
 * marketplace_product_catalogs.quantity_in_inventory.
 *
 * The join key is the InventoryItem GID persisted on the catalog mapping row,
 * falling back to the variant GID for rows created before a level was seen.
 * SKU is never used — Shopify does not enforce SKU uniqueness.
 *
 * Both guards live in ShopifyApplyService: an older remote level is discarded,
 * and a genuine local edit wins because leo is the inventory authority.
 */
class SyncShopifyInventoryJob extends AbstractShopifySyncJob
{
    protected function entityType(): string
    {
        return 'inventory';
    }

    protected function sync(ShopifyService $service, Carbon $since): array
    {
        $provider = $service->provider;
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        if (! $this->policyAllowsPull($provider, 'inventory')) {
            return ['processed' => 0, 'high_watermark' => null, 'details' => ['skipped' => 'sync_policy.inventory.direction=push']];
        }

        $applied = $unchanged = $staleRemote = $localNewer = $unmapped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchInventoryLevels($since) as $level) {
            $watermark = $this->maxWatermark($watermark, $level['external_updated_at']);

            $outcome = $applier->applyInventoryLevel($level, $this->dryRun);

            match ($outcome) {
                ShopifyApplyService::APPLIED => $applied++,
                ShopifyApplyService::UNCHANGED => $unchanged++,
                ShopifyApplyService::STALE_REMOTE => $staleRemote++,
                ShopifyApplyService::LOCAL_NEWER => $localNewer++,
                default => $unmapped++,
            };

            if ($this->dryRun && $outcome === ShopifyApplyService::APPLIED && count($samples) < 25) {
                $samples[] = ($level['sku'] ?: $level['external_variant_id']).' -> '.$level['available'];
            }
        }

        return [
            'processed' => $applied,
            'high_watermark' => $watermark,
            'details' => array_filter([
                'applied' => $applied,
                'unchanged' => $unchanged,
                'stale_remote_discarded' => $staleRemote,
                'local_newer_kept' => $localNewer,
                'unmapped' => $unmapped,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
