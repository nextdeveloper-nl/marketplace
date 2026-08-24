<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pulls Shopify customers into iam_users through the dedupe ladder in
 * ShopifyCustomerResolver.
 *
 * There is no marketplace_customers table by design: the person is an
 * iam_users row scoped to the merchant's account, and
 * marketplace_customer_mappings is only the external-id join.
 */
class SyncShopifyCustomersJob extends AbstractShopifySyncJob
{
    protected function entityType(): string
    {
        return 'customers';
    }

    protected function sync(ShopifyService $service, Carbon $since): array
    {
        $provider = $service->provider;
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        if (! $this->policyAllowsPull($provider, 'customers')) {
            return ['processed' => 0, 'high_watermark' => null, 'details' => ['skipped' => 'sync_policy.customers.direction=push']];
        }

        $mapped = $unmapped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchCustomers($since) as $raw) {
            $n = $adapter->normalizeCustomerData($raw);

            $watermark = $this->maxWatermark($watermark, $n['external_updated_at']);

            $mapping = $applier->applyCustomer($n, $this->dryRun);

            if ($this->dryRun) {
                if (count($samples) < 25) {
                    $samples[] = ($mapping ? 'mapped' : ($n['email'] ? 'would map/create' : 'skip (no email)'))
                        .': '.($n['email'] ?? '(guest)');
                }

                $n['email'] ? $mapped++ : $unmapped++;

                continue;
            }

            $mapping ? $mapped++ : $unmapped++;
        }

        return [
            'processed' => $mapped,
            'high_watermark' => $watermark,
            'details' => array_filter([
                'mapped' => $mapped,
                'skipped_no_identity' => $unmapped,
                'would_write' => $this->dryRun ? $samples : null,
            ], fn ($v) => $v !== null),
        ];
    }
}
