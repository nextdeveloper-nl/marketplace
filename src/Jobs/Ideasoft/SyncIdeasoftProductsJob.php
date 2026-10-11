<?php

namespace NextDeveloper\Marketplace\Jobs\Ideasoft;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftService;

/**
 * Pulls Ideasoft products (with their variants) into marketplace_products and
 * marketplace_product_catalogs, keyed through the mapping tables.
 */
class SyncIdeasoftProductsJob extends AbstractIdeasoftSyncJob
{
    protected function entityType(): string
    {
        return 'products';
    }

    protected function sync(IdeasoftService $service, Carbon $since): array
    {
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        $created = $updated = $skipped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchProducts($since) as $raw) {
            $n = $adapter->normalizeProductData($raw);

            // The watermark covers the parent and its variants: a variant edit moves the cursor.
            $watermark = $this->maxWatermark($watermark, $n['external_updated_at']);
            foreach ($n['variants'] as $variant) {
                $watermark = $this->maxWatermark($watermark, $variant['external_updated_at']);
            }

            $outcome = $applier->applyProduct($n, $this->dryRun);

            match ($outcome) {
                IdeasoftApplyService::CREATED => $created++,
                IdeasoftApplyService::UPDATED => $updated++,
                default => $skipped++,
            };

            if ($this->dryRun && $outcome !== IdeasoftApplyService::SKIPPED && count($samples) < 25) {
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
