<?php

namespace NextDeveloper\Marketplace\Jobs\Ideasoft;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftService;

/**
 * Reconciles Ideasoft stock into marketplace_product_catalogs.quantity_in_inventory.
 *
 * The adapter returns every level on every run (Ideasoft cannot filter stock by date and a stock
 * change does not reliably move updatedAt), so this is a reconciler: rows that already match are
 * counted as unchanged. Variants not yet created by the product sync are reported as unmapped.
 */
class SyncIdeasoftInventoryJob extends AbstractIdeasoftSyncJob
{
    protected function entityType(): string
    {
        return 'inventory';
    }

    protected function sync(IdeasoftService $service, Carbon $since): array
    {
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        $applied = $unchanged = $unmapped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchInventoryLevels($since) as $level) {
            $watermark = $this->maxWatermark($watermark, $level['external_updated_at']);

            $outcome = $applier->applyInventoryLevel($level, $this->dryRun);

            match ($outcome) {
                IdeasoftApplyService::APPLIED => $applied++,
                IdeasoftApplyService::UNMAPPED => $unmapped++,
                default => $unchanged++,
            };

            if ($this->dryRun && $outcome === IdeasoftApplyService::APPLIED && count($samples) < 25) {
                $samples[] = ($level['sku'] ?? $level['external_variant_id']).' -> '.$level['available'];
            }
        }

        return [
            'processed' => $applied,
            'high_watermark' => $watermark,
            'details' => array_filter([
                'applied' => $applied,
                'unchanged' => $unchanged,
                'unmapped' => $unmapped,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
