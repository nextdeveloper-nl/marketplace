<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Helpers\MappingExternalProduct;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\GetirAdapter;

/**
 * Service wrapper for Getir marketplace integration.
 * Responsibilities:
 *  - Load market/provider configuration
 *  - Initialize adapter
 *  - Fetch & process orders (normalize + persistence via existing services inside adapter pipeline if later extended)
 *  - Provide a similar surface to TrendyolGoYemekService for consistency
 */
class GetirService
{
    private GetirAdapter $adapter;
    private ?Markets $market = null;

    public function __construct(public Providers $provider)
    {
        $this->initializeService();
    }

    /**
     * Initialize service (market lookup + adapter instantiation)
     *
     * @throws NotFoundException
     * @throws Exception
     */
    private function initializeService(): void
    {
        if (!$this->provider->marketplace_market_id) {
            $message = "Provider '" . $this->provider->name . "' does not have a valid marketplace market ID.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }

        $this->market = $this->loadMarket($this->provider->marketplace_market_id);

        $config = $this->buildAdapterConfig();
        $this->adapter = new GetirAdapter($config, $this->provider);
    }

    /**
     * Load market record
     * @throws NotFoundException
     */
    private function loadMarket(int $marketId): Markets
    {
        $market = Markets::find($marketId);
        if (!$market) {
            $message = "Market with ID '{$marketId}' not found in the database.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }
        return $market;
    }

    /**
     * Build adapter configuration from provider api_config
     * @throws NotFoundException
     */
    private function buildAdapterConfig(): array
    {
        if (empty($this->provider->api_config)) {
            $message = "Provider '{$this->provider->name}' does not have API configuration.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }

        if (is_array($this->provider->api_config)) {
            $apiConfig = $this->provider->api_config;
        } else {
            $decoded = json_decode($this->provider->api_config, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $message = "Provider '{$this->provider->name}' has invalid API configuration format.";
                Log::warning(__METHOD__ . " - {$message}");
                throw new NotFoundException($message);
            }
            $apiConfig = $decoded;
        }

        if (empty($apiConfig['app_secret_key']) && empty($apiConfig['appSecretKey'])) {
            throw new NotFoundException("Getir configuration missing app_secret_key/appSecretKey");
        }
        if (empty($apiConfig['restaurant_secret_key']) && empty($apiConfig['restaurantSecretKey'])) {
            throw new NotFoundException("Getir configuration missing restaurant_secret_key/restaurantSecretKey");
        }

        return [
            'base_url' => $apiConfig['base_url'] ?? 'https://food-external-api-gateway.development.getirapi.com',
            'app_secret_key' => $apiConfig['app_secret_key'] ?? $apiConfig['appSecretKey'] ?? null,
            'restaurant_secret_key' => $apiConfig['restaurant_secret_key'] ?? $apiConfig['restaurantSecretKey'] ?? null,
            'restaurant_ids' => $apiConfig['restaurant_ids'] ?? $apiConfig['restaurantIds'] ?? null,
        ];
    }

    /**
     * Fetch and process orders starting from given date (single day by default)
     */
    public function fetchOrders(Carbon $date): void
    {
        try {
            $rawOrders = $this->adapter->fetchOrders($date);
            Log::info(__METHOD__ . ' - Fetched Getir orders', [
                'count' => is_countable($rawOrders) ? count($rawOrders) : 0,
                'date' => $date->format('Y-m-d'),
            ]);

            if (empty($rawOrders)) {
                return;
            }

            $this->processOrders($rawOrders);
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' - Error fetching Getir orders', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process each raw order: normalize then (optionally) persist or further handle.
     * For parity with Trendyol service we iterate items to map main product.
     */
    private function processOrders(array $orders): void
    {
        $processed = 0;
        $failed = 0;
        foreach ($orders as $index => $rawOrder) {
            try {
                $ok = $this->processSingleOrder($rawOrder, $index);
                $ok ? $processed++ : $failed++;
            } catch (Exception $e) {
                $failed++;
                Log::error(__METHOD__ . ' - Failed to process Getir order', [
                    'error' => $e->getMessage(),
                    'order' => $rawOrder,
                ]);
            }
        }
        Log::info(__METHOD__ . ' - Processing summary', [
            'total' => count($orders),
            'processed' => $processed,
            'failed' => $failed,
        ]);
    }

    private function processSingleOrder(array $rawOrder, int $index): bool
    {
        $normalized = $this->adapter->normalizeOrderData($rawOrder);
        $items = $normalized['items'] ?? [];
        if (empty($items)) {
            Log::warning(__METHOD__ . ' - No items in Getir order', ['external_id' => $normalized['external_order_id'] ?? null]);
            return false;
        }

        foreach ($items as $item) {
            $mappedProduct = MappingExternalProduct::mapMainProduct($item['id'], $this->provider, $item['name'] ?? 'Unknown');
            if (!$mappedProduct) {
                Log::warning(__METHOD__ . ' - Could not map product for Getir order item', ['item' => $item]);
                return false;
            }

            // Prepare order data similar to Trendyol service (no persistence here unless adapter extended)
            $orderData = $this->prepareOrderForDatabase($normalized, $mappedProduct, $item['external_line_id'] ?? null, $index + 1);
            // NOTE: Persistence is handled in Trendyol via adapter->updateOrCreateOrder + processOrderItems.
            // If required for Getir, replicate those features or extend adapter accordingly.
            Log::info(__METHOD__ . ' - Prepared Getir order data (persistence not yet implemented)', [
                'external_order_id' => $orderData['external_order_id'] ?? null,
            ]);
        }

        return true;
    }

    private function prepareOrderForDatabase(array $normalizedOrder, $mappedProduct, ?string $externalLineId, int $index): array
    {
        $orderData = $normalizedOrder;
        $orderData['marketplace_product_id'] = $mappedProduct->id;
        $orderData['marketplace_provider_id'] = $this->provider->id;
        $orderData['marketplace_market_id'] = $this->provider->marketplace_market_id;
        $orderData['external_line_id'] = $externalLineId;
        $orderData['tags'] = [
            $this->provider->name,
            $orderData['order_code'] ?? ($orderData['external_order_id'] ?? 'getir-order'),
        ];
        unset($orderData['items']);
        unset($orderData['order_code']);
        return $orderData;
    }

    /**
     * Update an external Getir order status via adapter.
     * Supported statuses (mapped internally by adapter): picking, shipped, delivered, cancelled, verify.
     */
    public function updateOrderStatus(string $externalOrderId, string $status): bool
    {
        try {
            $result = $this->adapter->updateOrderStatus($externalOrderId, $status);
            if ($result) {
                Log::info(__METHOD__ . ' - Getir order status updated', [
                    'external_order_id' => $externalOrderId,
                    'status' => $status,
                ]);
            } else {
                Log::warning(__METHOD__ . ' - Failed to update Getir order status', [
                    'external_order_id' => $externalOrderId,
                    'status' => $status,
                ]);
            }
            return $result;
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' - Exception updating Getir order status', [
                'external_order_id' => $externalOrderId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
