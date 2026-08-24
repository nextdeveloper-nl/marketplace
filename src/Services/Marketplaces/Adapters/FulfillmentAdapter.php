<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use NextDeveloper\Marketplace\Database\Models\Orders;

/**
 * Capability interface for marketplaces we can write order outcomes back to.
 *
 * This is the half of "two-way" that merchants actually notice: an order marked
 * shipped or refunded locally has to appear that way in their storefront.
 */
interface FulfillmentAdapter
{
    /**
     * Mark the given lines of an order as fulfilled remotely.
     *
     * @param  array<int, array<string, mixed>>  $lines  Empty means "all lines".
     */
    public function createFulfillment(Orders $order, array $lines = []): bool;

    /**
     * Cancel the order remotely.
     */
    public function cancelOrder(Orders $order, string $reason = ''): bool;

    /**
     * Refund the given lines of an order remotely.
     *
     * Implementations must send an idempotency key derived from the order and
     * the line set, never a random one: a retried job must not double-refund.
     *
     * @param  array<int, array<string, mixed>>  $lines  Empty means "full refund".
     */
    public function refundOrder(Orders $order, array $lines = []): bool;
}
