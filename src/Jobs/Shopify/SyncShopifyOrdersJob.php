<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pulls Shopify orders into marketplace_orders and marketplace_order_items.
 *
 * One local order per Shopify order, unlike Trendyol's row-per-package model;
 * the upsert key is (marketplace_provider_id, external_order_id).
 *
 * The shopper lands in customer_iam_user_id — never in iam_user_id, which is
 * the AuthorizationScope ownership column and must keep pointing at the
 * merchant. Guest checkouts get no identity at all; theirs stays in
 * customer_data. The scalar money columns stay shop-currency while
 * presentment_* records what the buyer was actually charged.
 */
class SyncShopifyOrdersJob extends AbstractShopifySyncJob
{
    protected function entityType(): string
    {
        return 'orders';
    }

    protected function sync(ShopifyService $service, Carbon $since): array
    {
        $provider = $service->provider;
        $adapter = $service->getAdapter();
        $applier = $service->applier();

        if (! $this->policyAllowsPull($provider, 'orders')) {
            return ['processed' => 0, 'high_watermark' => null, 'details' => ['skipped' => 'sync_policy.orders.direction=push']];
        }

        $created = $updated = $skipped = 0;
        $watermark = null;
        $samples = [];

        foreach ($adapter->fetchOrders($since) as $raw) {
            $n = $adapter->normalizeOrderData($raw);

            $watermark = $this->maxWatermark($watermark, $n['external_updated_at']);

            $outcome = $applier->applyOrder($n, $this->dryRun);

            match ($outcome) {
                ShopifyApplyService::CREATED => $created++,
                ShopifyApplyService::UPDATED => $updated++,
                default => $skipped++,
            };

            if ($this->dryRun && $outcome !== ShopifyApplyService::SKIPPED && count($samples) < 25) {
                $samples[] = $outcome.': '.$n['external_order_number']
                    .' '.$n['total_amount'].' '.($n['shop_currency_code'] ?? '');
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
