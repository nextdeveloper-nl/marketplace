<?php

namespace NextDeveloper\Marketplace\Jobs\Ebay;

use NextDeveloper\Marketplace\Services\Marketplaces\EbayApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\EbayService;

/**
 * Pulls eBay stock levels onto the catalog entries the products job created. Lighter than the
 * products job: it reads only the inventory item pages and skips the per-SKU offer and group
 * calls, so it can run far more often.
 */
class SyncEbayInventoryJob extends AbstractEbaySyncJob
{
    protected function entityType(): string
    {
        return 'inventory';
    }

    protected function sync(EbayService $service, bool $resume): array
    {
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        $applied = $unchanged = $unmapped = 0;
        $samples = [];

        $offset = 0;

        do {
            $page = $adapter->fetchInventoryPage($offset);

            foreach ($page['items'] as $item) {
                $level = $adapter->inventoryLevel($item);

                $outcome = $applier->applyInventoryLevel($level, $this->dryRun);

                match ($outcome) {
                    EbayApplyService::APPLIED => $applied++,
                    EbayApplyService::UNMAPPED => $unmapped++,
                    default => $unchanged++,
                };

                if ($this->dryRun && $outcome === EbayApplyService::APPLIED && count($samples) < 25) {
                    $samples[] = $level['sku'].' -> '.$level['available'];
                }
            }

            $offset = $page['next_offset'];
        } while ($page['has_more']);

        return [
            'processed' => $applied,
            'details' => array_filter([
                'applied' => $applied,
                'unchanged' => $unchanged,
                'unmapped' => $unmapped,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
