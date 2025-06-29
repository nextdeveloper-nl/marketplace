<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\Providers;
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
        // Set admin privileges for provider access
        UserHelper::setAdminAsCurrentUser();

        // Load provider's marketplace market
        if (!$this->provider->marketplace_market_id) {
            $message = "Provider '" . $this->provider->name . "' does not have a valid marketplace market ID.";
            Log::warning(__METHOD__ . " - {$message}");
            throw new NotFoundException($message);
        }

        $this->market = $this->loadMarket($this->provider->marketplace_market_id);

        // Initialize adapter with configuration
        $config = $this->buildAdapterConfig();
        $this->adapter = new TrendyolGoYemekAdapter($config);
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
        }
        else {
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

            if (empty($orders)) {
                Log::info(__METHOD__ . " - No orders found for date: " . $date->format('Y-m-d H:i:s'));
                return;
            }

            $this->processOrders($orders);

        } catch (Exception $e) {
            Log::error(__METHOD__ . " - Error fetching orders: " . $e->getMessage(), [
                'date' => $date->format('Y-m-d H:i:s'),
                'exception' => $e
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

        foreach ($orders as $rawOrder) {
            try {
                if ($this->processSingleOrder($rawOrder)) {
                    $processedCount++;
                } else {
                    $failedCount++;
                }
            } catch (Exception $e) {
                $failedCount++;
                Log::error(__METHOD__ . " - Error processing individual order", [
                    'order' => $rawOrder,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info(__METHOD__ . " - Order processing completed", [
            'total' => count($orders),
            'processed' => $processedCount,
            'failed' => $failedCount
        ]);
    }

    /**
     * Process a single order
     *
     * @param array $rawOrder Raw order data from API
     * @return bool Success status
     */
    private function processSingleOrder(array $rawOrder): bool
    {
        // Normalize order data
        $normalizedOrder = $this->adapter->normalizeOrderData($rawOrder);
        $orderItems = $normalizedOrder['items'] ?? collect();

        // Find the main product
        $mainProduct = $this->findMainProduct($orderItems);
        if (!$mainProduct) {
            Log::warning(__METHOD__ . " - No main product found", ['order' => $normalizedOrder]);
            return false;
        }

        // Map product to catalog
        $mappedProduct = $this->adapter->mapProduct($mainProduct, $this->provider->id, true);
        if (!$mappedProduct) {
            Log::warning(__METHOD__ . " - No product mapping found", ['product' => $mainProduct]);
            return false;
        }

        // Prepare order for a database
        $orderData = $this->prepareOrderForDatabase($normalizedOrder, $mappedProduct);

        // Create or update order
        $order = $this->adapter->updateOrCreateOrder($orderData);
        if (!$order) {
            Log::error(__METHOD__ . " - Failed to create/update order", ['order_data' => $orderData]);
            return false;
        }

        // Process order items
        $this->adapter->processOrderItems($orderItems, $order->id, $this->provider->id);

        Log::info(__METHOD__ . " - Order processed successfully", [
            'external_order_id' => $orderData['external_order_id'],
            'internal_order_id' => $order->id
        ]);

        return true;
    }

    /**
     * Find the main product from order items
     */
    private function findMainProduct($orderItems)
    {
        if (!$orderItems || $orderItems->isEmpty()) {
            return null;
        }

        return $orderItems->where('main_item', true)->first();
    }

    /**
     * Prepare order data for database insertion
     */
    private function prepareOrderForDatabase(array $normalizedOrder, $mappedProduct): array
    {
        // Determine product ID based on mapping
        $productId = $mappedProduct->parent_marketplace_product_id ?? $mappedProduct->id;

        $orderData = $normalizedOrder;
        $orderData['marketplace_product_id'] = $productId;
        $orderData['marketplace_provider_id'] = $this->provider->id;
        $orderData['marketplace_market_id'] = $this->provider->marketplace_market_id;
        $orderData['iam_account_id'] = $this->market->iam_account_id;
        $orderData['iam_user_id'] = $this->market->iam_user_id;

        // Remove items array as it's processed separately
        unset($orderData['items']);

        return $orderData;
    }
}