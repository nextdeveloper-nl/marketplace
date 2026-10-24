<?php

namespace NextDeveloper\Marketplace\Jobs\Ebay;

use NextDeveloper\Marketplace\Services\Marketplaces\EbayApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\EbayService;

/**
 * Pulls the seller's eBay inventory (items, offers, variation groups) into marketplace_products
 * and marketplace_product_catalogs, keyed through the mapping tables.
 */
class SyncEbayProductsJob extends AbstractEbaySyncJob
{
    protected function entityType(): string
    {
        return 'products';
    }

    protected function sync(EbayService $service, bool $resume): array
    {
        $adapter = $service->getAdapter();
        $applier = $service->applier();
        $entity = $this->entityType();

        $created = $updated = $skipped = $unmapped = 0;
        $samples = [];

        $start = $resume ? $service->scanOffset($entity) : 0;

        // The position is saved after every page, so a crash resumes at the page it died on.
        $savePosition = $this->dryRun
            ? null
            : fn (?int $next) => $service->saveScanOffset($entity, $next);

        foreach ($adapter->scanProducts($start, $savePosition) as $raw) {
            $product = $adapter->normalizeProductData($raw);
            $variant = $adapter->normalizeVariantData($raw['item'], $raw['offers'], $raw['group']);

            $productOutcome = $applier->applyProduct($product, $this->dryRun);
            $variantOutcome = $applier->applyVariant($product['external_id'], $variant, $product['name'], $this->dryRun);

            // Counted per SKU: the catalog entry is what a seller thinks of as "an item".
            match ($variantOutcome) {
                EbayApplyService::CREATED => $created++,
                EbayApplyService::UPDATED => $updated++,
                EbayApplyService::UNMAPPED => $unmapped++,
                default => $skipped++,
            };

            if ($productOutcome === EbayApplyService::CREATED || $productOutcome === EbayApplyService::UPDATED) {
                // A product-level change (title, images, ...) is a write even if the SKU row was unchanged.
                if ($variantOutcome === EbayApplyService::SKIPPED) {
                    $skipped--;
                    $updated++;
                }
            }

            if ($this->dryRun && $variantOutcome !== EbayApplyService::SKIPPED && count($samples) < 25) {
                $samples[] = $variantOutcome.': '.$product['name'].' ['.$variant['sku'].']';
            }
        }

        return [
            'processed' => $created + $updated,
            'details' => array_filter([
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'unmapped' => $unmapped ?: null,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
