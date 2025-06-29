<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Carbon\Carbon;


/**
 * Interface for marketplace adapters
 *
 * This interface defines the contract for all marketplace adapters,
 * ensuring consistent API across different marketplace implementations.
 */
interface MarketplaceAdapter
{
    /**
     * Authenticate with the marketplace API
     *
     * @param array $credentials Authentication credentials (api_key, api_secret, etc.)
     * @return bool True if authentication successful, false otherwise
     */
    public function authenticate(array $credentials): bool;

    /**
     * Fetch orders from the marketplace since a specific date
     *
     * @param Carbon|\Illuminate\Support\Carbon $since Starting date to fetch orders from
     * @return array Array of raw order data from the marketplace
     */
    public function fetchOrders(\Illuminate\Support\Carbon|Carbon $since): array;

    /**
     * Update order status in the marketplace
     *
     * @param string $orderId External order ID in the marketplace
     * @param string $status New status to set (picking, shipped, delivered, cancelled)
     * @return bool True if update successful, false otherwise
     */
    public function updateOrderStatus(string $orderId, string $status): bool;

    /**
     * Normalize raw order data to standardized format
     *
     * @param array $rawOrder Raw order data from marketplace API
     * @return array Normalized order data following our internal schema
     */
    public function normalizeOrderData(array $rawOrder): array;

    /**
     * Get webhook configuration for the marketplace
     *
     * @return array Webhook configuration including supported events, security, etc.
     */
    public function getWebhookConfig(): array;
}
