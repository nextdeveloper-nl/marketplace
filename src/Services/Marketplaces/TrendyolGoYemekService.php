<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Helpers\MappingExternalProduct;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\TrendyolGoYemekAdapter;

class TrendyolGoYemekService
{

    private TrendyolGoYemekAdapter $adapter;
    private ?Markets $market = null;

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function __construct(public Providers $provider)
    {
        $this->initializeService();
    }

    /**
     * Initialize the service with configuration and dependencies
     *
     * @throws NotFoundException
     * @throws Exception
     */
    private function initializeService(): void
    {

        // Load provider's marketplace market
        if (!$this->provider->marketplace_market_id) {
            $message = "Provider '" . $this->provider->name . "' does not have a valid marketplace market ID.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }

        $this->market = $this->loadMarket($this->provider->marketplace_market_id);

        // Initialize adapter with configuration
        $config = $this->buildAdapterConfig();
        $this->adapter = new TrendyolGoYemekAdapter($config, $this->provider);
    }

    /**
     * Load market from a database
     *
     * @param int $marketId
     * @return Markets
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
     * Build a configuration array for the adapter
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
            //check if api_config is a valid JSON string
            if (json_decode($this->provider->api_config) === null && json_last_error() !== JSON_ERROR_NONE) {
                $message = "Provider '{$this->provider->name}' has invalid API configuration format.";
                Log::warning(__METHOD__ . " - {$message}");
                throw new NotFoundException($message);
            }

            $apiConfig = json_decode($this->provider->api_config, true);
        }

        if (empty($apiConfig['api_key']) || empty($apiConfig['supplier_id']) || empty($apiConfig['api_secret'])) {
            $message = "Provider '{$this->provider->name}' API configuration is incomplete.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }

        return [
            'api_key' => $apiConfig['api_key'],
            'supplier_id' => $apiConfig['supplier_id'],
            'api_secret' => $apiConfig['api_secret'],
            'default_ingredient_ids' => $apiConfig['default_ingredient_ids'] ?? [],
            'default_ingredient_source_name' => $apiConfig['default_ingredient_source_name'] ?? '',
        ];
    }

    /**
     * Fetch and process orders from the specified date
     *
     * @param Carbon|\Illuminate\Support\Carbon $date Starting date for order fetching
     * @throws Exception
     */
    public function fetchOrders(Carbon|\Illuminate\Support\Carbon $date): void
    {
        try {
            $orders = $this->adapter->fetchOrders($date);

            Log::info(__METHOD__ . " - Fetched orders for date: " . $date->format('Y-m-d H:i:s'), [
                'orders' => $orders,
            ]);

            if (empty($orders)) {
                Log::info(__METHOD__ . " - No orders found for date: " . $date->format('Y-m-d H:i:s'));
                return;
            }

            $this->processOrders($orders);
        } catch (Exception $e) {
            Log::error(__METHOD__ . " - Error fetching orders: " . $e->getMessage(), [
                'date' => $date->format('Y-m-d H:i:s'),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Process a batch of orders
     */
    private function processOrders(array $orders): void
    {
        $processedCount = 0;
        $failedCount = 0;

        foreach ($orders as $index => $rawOrder) {
            try {
                if ($this->processSingleOrder($rawOrder, $index)) {
                    $processedCount++;
                } else {
                    $failedCount++;
                }
            } catch (Exception $e) {
                $failedCount++;
                Log::error(__METHOD__ . " - Error processing order", [
                    'order' => $rawOrder,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info(__METHOD__ . " - Order processing completed", [
            'total' => count($orders),
            'processed' => $processedCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Process a single order
     *
     * @param array $rawOrder Raw order data from API
     * @return bool Success status
     */
    private function processSingleOrder(array $rawOrder, int $index): bool
    {
        // Normalize order data
        $normalizedOrder = $this->adapter->normalizeOrderData($rawOrder);
        $orderItems = $normalizedOrder['items'] ?? collect();

        foreach ($orderItems as $orderItem) {
            // Map product to catalog
            $mappedProduct = MappingExternalProduct::mapMainProduct($orderItem['id'], $this->provider, $orderItem['name']);
            if (!$mappedProduct) return false;

            // Prepare order for a database
            $orderData = $this->prepareOrderForDatabase(
                $normalizedOrder,
                $mappedProduct,
                $orderItem['external_line_id'],
                $index + 1
            );

            // Create or update order
            $order = $this->adapter->updateOrCreateOrder($orderData);
            if ($order == null) {
                Log::error(__METHOD__ . " - Failed to create/update order", ['order_data' => $orderData]);
                return false;
            }

            /**
             * Note: The 'new' key is used to determine if the order is new or already exists.
             * If 'new' is false, it means the order already exists in the database,
             * so we skip processing the items for this order.
             * This is to avoid duplicate processing of existing orders.
             * Because the external order ID don't change, we can safely skip processing
             */
            if (isset($order['new_order']) && !$order['new_order']) {
                Log::info(__METHOD__ . " - Order already exists, skipping item processing", [
                    'external_order_id' => $orderData['external_order_id'],
                    'internal_order_id' => $order['id'],
                ]);
                continue;
            }

            // Process order items
            $this->adapter->processOrderItems($orderItems, $order['id']);

            Log::info(__METHOD__ . " - Order processed successfully", [
                'external_order_id' => $orderData['external_order_id'],
                'internal_order_id' => $order['id'],
            ]);
        }

        return true;
    }

    /**
     * Prepare order data for database insertion
     */
    private function prepareOrderForDatabase(array $normalizedOrder, $mappedProduct, ?string $externalLineId, int $index): array
    {

        $orderData = $normalizedOrder;
        $orderData['marketplace_product_id'] = $mappedProduct->id;
        $orderData['marketplace_provider_id'] = $this->provider->id;
        $orderData['marketplace_market_id'] = $this->provider->marketplace_market_id;
        $orderData['external_line_id'] = $externalLineId;
        $orderData['tags'] = [
            $this->provider->name,
            $orderData['order_code'],
        ];

        // Remove items array as it's processed separately
        unset($orderData['items']);
        unset($orderData['order_code']);

        return $orderData;
    }
}