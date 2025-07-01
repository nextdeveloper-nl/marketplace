<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Illuminate\Support\Carbon;
use Exception;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Helpers\MappingExternalProduct;
use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Services\OrderItemsService;
use NextDeveloper\Marketplace\Services\OrdersService;

class TrendyolGoYemekAdapter implements MarketplaceAdapter
{
    private const DEFAULT_TIMEOUT = 60;

    private string $modifierGroupId = '981117';
    private string $modifierGroupDefaultName = 'Malzemeler';


    private const STATUS_MAP = [
        'new' => 'Created',
        'picking' => 'Picking',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    private const DELIVERY_METHOD_MAP = [
        'GO' => 'platform_delivery',
        'PICKUP' => 'self_pickup',
        'STORE' => 'restaurant_delivery',
    ];

    private array $config;
    private ?string $authToken = null;
    private ?string $supplierId = null;
    private string $baseUri;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUri = rtrim($config['base_url'] ?? 'https://api.trendyol.com/', '/');
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * Fetch orders from Trendyol Go Yemek API
     */
    public function fetchOrders(Carbon|\Carbon\Carbon $since): array
    {
        try {
            $response = $this->makeOrdersRequest($since);

            if ($response['status_code'] !== 200) {
                $this->logApiError('Failed to fetch orders', $response);
                return [];
            }

            return $this->parseOrdersResponse($response);

        } catch (Exception $e) {
            $this->logException('Error fetching orders', $e);
            return [];
        } catch (Throwable $e) {
            $this->logException('Throwable error fetching orders', $e);
            return [];
        }
    }

    /**
     * Make an HTTP request to fetch orders
     */
    private function makeOrdersRequest(Carbon|\Carbon\Carbon $since): array
    {
        $supplierId = (string) $this->config['supplier_id'];
        $apiKey = (string) $this->config['api_key'];
        $apiSecret = (string) $this->config['api_secret'];

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
            'x-agentname' => 'application/json',
            'x-executor-user' => 'application/json',
        ];

        $query = $this->buildOrdersQuery($since);

        return $this->curlRequest('GET', "/mealgw/suppliers/{$supplierId}/packages", $headers, [], $query);
    }

    /**
     * Build query parameters for orders request
     */
    private function buildOrdersQuery(\Carbon\Carbon|Carbon $since): array
    {
        // Clone the DateTime objects to avoid modifying the original
        $endDate = clone $since;


        // convert to timestamp milliseconds
        $sinceMilliseconds = (int) ($since->startOfDay()->timestamp * 1000);
        $endDateMilliseconds = (int) ($endDate->endOfDay()->timestamp * 1000);

        return [
            'packageModificationStartDate' => $sinceMilliseconds,
            'packageModificationEndDate' => $endDateMilliseconds,
        ];
    }

    /**
     * Parse orders response from API
     */
    private function parseOrdersResponse(array $response): array
    {
        $data = json_decode($response['body'], true) ?: [];

        $this->logApiSuccess('Fetched orders', [
            'response_length' => strlen($response['body']),
            'supplier_id' => $this->config['supplier_id'],
        ]);

        if (empty($data) || !isset($data['content'])) {
            Log::warning(__METHOD__ . ' - No valid order data returned', [
                'response_body' => $response['body'],
                'supplier_id' => $this->config['supplier_id'],
            ]);
            return [];
        }

        return $data['content'];
    }

    /**
     * Authenticate with Trendyol Go Yemek API
     */
    public function authenticate(array $credentials): bool
    {
        return true;
    }

    /**
     * Normalize raw order data to the standard format
     */
    public function normalizeOrderData(array $rawOrder): array
    {
        try {
            return [
                'external_order_id' => $rawOrder['orderId'] ?? null,
                'external_order_number' => $rawOrder['orderNumber'] ?? null,
                'status' => $rawOrder['packageStatus'] ?? 'Created',
                'ordered_at' => $this->parseTimestamp($rawOrder['packageCreationDate'] ?? null),
                'delivered_at' => $this->parseTimestamp($rawOrder['lastModifiedDate'] ?? null),
                'customer_data' => $this->normalizeCustomerData($rawOrder['customer'] ?? []),
                'delivery_address' => $this->buildFullAddress($rawOrder['address'] ?? []),
                'subtotal_amount' => (float) ($rawOrder['totalPrice'] ?? 0),
                'discount_amount' => $this->extractDiscountAmount($rawOrder),
                'total_amount' => (float) ($rawOrder['totalPrice'] ?? 0),
                'order_type' => 'delivery',
                'delivery_method' => $this->mapDeliveryMethod($rawOrder),
                'estimated_delivery_time' => $this->extractEstimatedDeliveryTime($rawOrder),
                'raw_order_data' => $rawOrder,
                'last_sync_at' => now()->toISOString(),
                'customer_note' => $rawOrder['customerNote'] ?? null,
                'items' => $this->normalizeOrderItems($rawOrder['lines'] ?? []),
            ];
        } catch (Exception $e) {
            $this->logException('Error normalizing order data', $e);
            return $this->buildMinimalNormalizedOrder($rawOrder, $e);
        }
    }

    /**
     * Normalize customer data
     */
    private function normalizeCustomerData(array $customer): array
    {
        return [
            'id' => $customer['id'] ?? null,
            'name' => $customer['firstName'] ?? 'Unknown Customer',
            'lastname' => $customer['lastName'] ?? '',
            'email' => $customer['email'] ?? 'unknown@email.com',
        ];
    }

    /**
     * Build minimal normalized order on error
     */
    private function buildMinimalNormalizedOrder(array $rawOrder, Exception $e): array
    {
        return [
            'external_order_id' => (string) ($rawOrder['orderNumber'] ?? $rawOrder['id'] ?? uniqid()),
            'external_order_number' => $rawOrder['orderNumber'] ?? null,
            'status' => $rawOrder['status'] ?? 'Created',
            'normalized_status' => 'new',
            'raw_order_data' => $rawOrder,
            'sync_status' => 'error',
            'sync_error_message' => $e->getMessage(),
        ];
    }

    /**
     * Parse timestamp string to formatted date
     */
    private function parseTimestamp(?string $timestamp): ?string
    {
        if (!$timestamp) {
            return null;
        }

        try {
            // Convert to DateTime object
            $dateTime = Carbon::createFromTimestamp($timestamp / 1000);
            // Format to Y-m-d H:i:s
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            Log::warning('Failed to parse timestamp', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build full address from components
     */
    private function buildFullAddress(array $address): string
    {
        $components = [
            'address1', 'address2', 'city', 'district', 'neighborhood',
            'apartmentNumber', 'floor', 'doorNumber', 'postalCode',
            'countryCode', 'phone'
        ];

        $parts = array_filter(array_map(fn($key) => $address[$key] ?? null, $components));

        return implode(', ', $parts);
    }

    /**
     * Map delivery method from raw data
     */
    private function mapDeliveryMethod(array $rawOrder): string
    {
        $deliveryType = $rawOrder['deliveryType'] ?? 'GO';
        return self::DELIVERY_METHOD_MAP[$deliveryType] ?? 'delivery';
    }

    /**
     * Normalize order items
     */
    private function normalizeOrderItems(array $lines): Collection
    {
        $normalizedItems = collect();

        foreach ($lines as $line) {
            // Add main item
            $normalizedItems->push([
                'id' => $line['productId'],
                'price_per_item' => (float) ($line['price'] ?? 0),
                'total_price' => (float) ($line['price'] ?? 0),
                'main_item' => true,
                'modifier_group_ids' => $line['removedIngredients'] ? [] : $this->extractModifierGroupId($line),
            ]);

            // Add extra ingredients
            $this->addExtraIngredients($normalizedItems, $line['extraIngredients'] ?? []);

            // Add removed ingredients info
            $this->addRemovedIngredientsInfo($normalizedItems, $line['removedIngredients'] ?? []);
        }

        return $normalizedItems;
    }

    /**
     * Add extra ingredients to normalized items
     */
    private function addExtraIngredients(Collection $items, array $extraIngredients): void
    {
        foreach ($extraIngredients as $ingredient) {
            $items->push([
                'id' => $ingredient['id'],
                'price_per_item' => (float) ($ingredient['price'] ?? 0),
                'total_price' => (float) ($ingredient['price'] ?? 0),
                'main_item' => false,
            ]);
        }
    }

    /**
     * Add removed ingredients information
     */
    private function addRemovedIngredientsInfo(Collection $items, array $removedIngredients): void
    {

        if (empty($removedIngredients)) {
            return;
        }

        $removedIngredientIds = array_column($removedIngredients, 'id');

        $items->push([
            'removed_ingredients' => $removedIngredientIds,
            'main_item' => false,
        ]);
    }

    /**
     * Update order status in API
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            if (!$this->ensureAuthenticated()) {
                throw new Exception('Authentication failed');
            }

            $endpoint = $this->getStatusUpdateEndpoint($status, $orderId);
            if (!$endpoint) {
                Log::warning(__METHOD__ . ' - No endpoint found for status', [
                    'order_id' => $orderId,
                    'status' => $status,
                ]);
                return false;
            }

            $payload = $this->buildStatusUpdatePayload($status);
            $headers = ['Authorization' => 'Basic ' . $this->authToken];

            $response = $this->curlRequest('PUT', $endpoint, $headers, $payload);
            $success = $response['status_code'] === 200;

            $this->logStatusUpdateResult($success, $orderId, $status, $response);

            return $success;

        } catch (Exception $e) {
            $this->logException('Error updating order status', $e, [
                'order_id' => $orderId,
                'status' => $status,
            ]);
            return false;
        }
    }

    /**
     * Ensure we have valid authentication
     */
    private function ensureAuthenticated(): bool
    {
        if ($this->authToken && $this->supplierId) {
            return true;
        }

        $credentials = [
            'api_key' => $this->config['api_key'] ?? '',
            'api_secret' => $this->config['api_secret'] ?? '',
            'supplier_id' => $this->config['supplier_id'] ?? '',
        ];

        return $this->authenticate($credentials);
    }

    /**
     * Get endpoint for status updates
     */
    private function getStatusUpdateEndpoint(string $status, string $orderNumber): ?string
    {
        $statusEndpoints = [
            'picking' => "sapigw/suppliers/{$this->supplierId}/orders/{$orderNumber}/status",
            'shipped' => "sapigw/suppliers/{$this->supplierId}/orders/{$orderNumber}/status",
            'delivered' => "sapigw/suppliers/{$this->supplierId}/orders/{$orderNumber}/status",
            'cancelled' => "sapigw/suppliers/{$this->supplierId}/orders/{$orderNumber}/status",
        ];

        return $statusEndpoints[$status] ?? null;
    }

    /**
     * Build payload for status update
     */
    private function buildStatusUpdatePayload(string $status): array
    {
        $mappedStatus = self::STATUS_MAP[$status] ?? ucfirst($status);

        $payload = [
            'status' => $mappedStatus,
            'timestamp' => now()->toISOString(),
        ];

        // Add status-specific fields
        switch ($status) {
            case 'shipped':
                $payload['trackingNumber'] = $this->config['default_tracking_number'] ?? null;
                $payload['trackingUrl'] = $this->config['default_tracking_url'] ?? null;
                break;

            case 'delivered':
                $payload['deliveryDate'] = now()->toISOString();
                break;

            case 'cancelled':
                $payload['cancelReason'] = $this->config['default_cancel_reason'] ?? 'Restaurant unable to fulfill order';
                break;
        }

        return $payload;
    }

    /**
     * Get webhook configuration
     */
    public function getWebhookConfig(): array
    {
        return [
            'supported_events' => [
                'order.created' => [
                    'description' => 'New order notification',
                    'payload_format' => 'json',
                ],
                'order.status_changed' => [
                    'description' => 'Order status update notification',
                    'payload_format' => 'json',
                ],
            ],
            'security' => [
                'authentication_method' => 'basic_auth',
                'requires_ssl' => true,
            ],
            'retry_policy' => [
                'max_attempts' => 3,
                'retry_delay' => 300,
                'exponential_backoff' => true,
            ],
            'timeout' => 30,
            'notes' => 'Trendyol Go Yemek uses HTTP Basic Authentication for API access',
        ];
    }

    /**
     * Extract discount amount from raw order
     */
    private function extractDiscountAmount(array $rawOrder): float
    {
        $totalDiscount = 0;

        if (isset($rawOrder['coupon']['totalSellerAmount'])) {
            $totalDiscount += (float) $rawOrder['coupon']['totalSellerAmount'];
        }

        if (isset($rawOrder['promotions']) && is_array($rawOrder['promotions'])) {
            foreach ($rawOrder['promotions'] as $promotion) {
                if (isset($promotion['totalSellerAmount'])) {
                    $totalDiscount += (float) $promotion['totalSellerAmount'];
                }
            }
        }

        return round($totalDiscount, 2);
    }

    /**
     * Extract estimated delivery time
     */
    private function extractEstimatedDeliveryTime(array $rawOrder): ?string
    {
        if (!isset($rawOrder['estimatedPickupTimeMin'], $rawOrder['estimatedPickupTimeMax'])) {
            return null;
        }

        try {
            $minTime = (int) $rawOrder['estimatedPickupTimeMin'];

            // Use only the minimum time (start of the delivery window)
            $minDate = Carbon::createFromTimestamp($minTime / 1000);

            // Return in Y-m-d H:i:s format to ensure proper parsing
            return $minDate->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            Log::warning('Failed to parse estimated delivery time', [
                'raw_order' => $rawOrder,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update or create order
     */
    public function updateOrCreateOrder($order): ?Orders
    {
        try {
            $existingOrder = Orders::where('external_order_id', $order['external_order_id'])
                ->where('marketplace_provider_id', $order['marketplace_provider_id'])
                ->first();

            if ($existingOrder) {
                OrdersService::update($existingOrder->uuid, $order);
                Log::info('Updated existing order', [
                    'external_order_id' => $order['external_order_id'],
                    'marketplace_provider_id' => $order['marketplace_provider_id'],
                ]);
                return $existingOrder;
            }

            $newOrder = OrdersService::create($order);
            Log::info('Created new order', [
                'external_order_id' => $order['external_order_id'],
                'marketplace_provider_id' => $order['marketplace_provider_id'],
            ]);

            return $newOrder;

        } catch (Exception $e) {
            Log::error('Failed to update or create order', [
                'order' => $order,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process order items
     */
    public function processOrderItems($items, $orderId, $providerId): void
    {
        try {
            // Clear existing order items
            OrdersService::deleteOrderItemsByOrderId($orderId);

            // Process each item
            foreach ($items as $item) {
                $this->processOrderItem($item, $orderId, $providerId);

                // process removed ingredients if present
                if (!empty($item['removed_ingredients'])) {
                    $this->processRemovedIngredients($item['removed_ingredients'], $orderId, $providerId);
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to process order items', [
                'order_id' => $orderId,
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a single order item
     */
    private function processOrderItem(array $item, $orderId, $providerId): void
    {
        try {
            $mappedProduct = MappingExternalProduct::mapProduct($item, $providerId);

            if (!$mappedProduct) {
                Log::warning('No product mapping found for order item', [
                    'order_id' => $orderId,
                    'item_id' => $item['id'] ?? 'unknown',
                    'provider_id' => $providerId,
                ]);
                return;
            }

            $orderItemData = [
                'marketplace_order_id' => $orderId,
                'marketplace_product_catalog_id' => $mappedProduct->id,
                'price_per_item' => (float) ($item['price_per_item'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? 0),
            ];

            OrderItemsService::create($orderItemData);

            if (!empty($item['modifier_group_ids'])) {
               if (in_array($this->modifierGroupId, $item['modifier_group_ids'])) {

                   $getModifierGroupProduct = Products::where('name', $this->modifierGroupDefaultName)
                          ->first();

                     if ($getModifierGroupProduct) {
                         $getModifierGroupProductCatalogs = ProductCatalogs::where('marketplace_product_id', $getModifierGroupProduct->id)
                             ->get();

                            foreach ($getModifierGroupProductCatalogs as $catalog) {
                                    $orderItemData['marketplace_product_catalog_id'] = $catalog->id;
                                    $orderItemData['price_per_item'] = 0; // Default price for a modifier group
                                    $orderItemData['total_price'] = 0; // Default total price for a modifier group

                                    OrderItemsService::create($orderItemData);
                            }
                     }

               }
            }

        } catch (Exception $e) {
            Log::error('Failed to process individual order item', [
                'order_id' => $orderId,
                'item' => $item,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process removed ingredients for an order item
     */
    private function processRemovedIngredients(array $removedIngredientIds, $orderId, $providerId): void
    {
        try {
            $mappedIngredients = $this->mapRemovedIngredients($removedIngredientIds, $providerId);

            if (empty($mappedIngredients['product_ids'])) {
                return;
            }

            $this->createRemovedIngredientItems($mappedIngredients, $orderId);

        } catch (Exception $e) {
            Log::error('Failed to process removed ingredients', [
                'order_id' => $orderId,
                'removed_ingredients' => $removedIngredientIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map removed ingredient IDs to internal products
     */
    private function mapRemovedIngredients(array $ingredientIds, $providerId): array
    {
        $mappedCatalogIds = [];
        $productIds = [];

        foreach ($ingredientIds as $ingredientId) {
            $mappedIngredient = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
                ->where('marketplace_provider_id', $providerId)
                ->where('external_catalog_id', $ingredientId)
                ->first();

            if (!$mappedIngredient) {
                Log::warning('No mapping found for removed ingredient', [
                    'ingredient_id' => $ingredientId,
                    'provider_id' => $providerId,
                ]);
                continue;
            }

            $mappedCatalogIds[] = $mappedIngredient->marketplace_product_catalog_id;

            $productCatalog = ProductCatalogs::find($mappedIngredient->marketplace_product_catalog_id);
            if ($productCatalog) {
                $productIds[] = $productCatalog->marketplace_product_id;
            }
        }

        return [
            'catalog_ids' => $mappedCatalogIds,
            'product_ids' => $productIds,
        ];
    }

    /**
     * Create order items for removed ingredients
     */
    private function createRemovedIngredientItems(array $mappedIngredients, $orderId): void
    {

        $productCatalogs = ProductCatalogs::whereIn('marketplace_product_id', $mappedIngredients['product_ids'])
            ->whereNotIn('id', $mappedIngredients['catalog_ids'])
            ->get();

        foreach ($productCatalogs as $productCatalog) {
            $orderItemData = [
                'marketplace_order_id' => $orderId,
                'marketplace_product_catalog_id' => $productCatalog->id,
                'price_per_item' => 0, // Removed ingredients have no price
                'total_price' => 0,
            ];

            OrderItemsService::create($orderItemData);
        }
    }

    /**
     * Make HTTP request using cURL
     */
    private function curlRequest(string $method, string $endpoint, array $headers = [], array $data = [], array $query = []): array
    {
        $url = $this->buildRequestUrl($endpoint, $query);
        $ch = $this->initializeCurl($url, $method, $headers, $data);

        try {
            return $this->executeCurlRequest($ch);
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Build complete URL for request
     */
    private function buildRequestUrl(string $endpoint, array $query): string
    {
        $url = $this->baseUri . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Initialize cURL handle
     */
    private function initializeCurl(string $url, string $method, array $headers, array $data)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $this->buildCurlHeaders($headers),
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        return $ch;
    }

    /**
     * Build cURL headers array
     */
    private function buildCurlHeaders(array $headers): array
    {
        $defaultHeaders = [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0',
        ];

        $curlHeaders = $defaultHeaders;
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        return $curlHeaders;
    }

    /**
     * Execute cURL request and handle response
     */
    private function executeCurlRequest($ch): array
    {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if ($errno) {
            throw new Exception("cURL error: $error", $errno);
        }

        return [
            'status_code' => $httpCode,
            'body' => $response,
        ];
    }

    // Logging helper methods

    /**
     * Log API error
     */
    private function logApiError(string $message, array $response): void
    {
        Log::error(__METHOD__ . " - {$message}", [
            'status_code' => $response['status_code'],
            'response_body' => $response['body'],
        ]);
    }

    /**
     * Log API success
     */
    private function logApiSuccess(string $message, array $context = []): void
    {
        Log::info(__METHOD__ . " - {$message}", $context);
    }

    /**
     * Log exception with context
     */
    private function logException(string $message, Throwable $e, array $context = []): void
    {
        Log::error(__METHOD__ . " - {$message}", array_merge($context, [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]));
    }


    /**
     * Log status update result
     */
    private function logStatusUpdateResult(bool $success, string $orderId, string $status, array $response): void
    {
        if ($success) {
            Log::info(__METHOD__ . ' - Successfully updated order status', [
                'order_id' => $orderId,
                'status' => $status,
                'response_length' => strlen($response['body']),
            ]);
        } else {
            Log::error(__METHOD__ . ' - Failed to update order status', [
                'order_id' => $orderId,
                'status' => $status,
                'response_body' => $response['body'],
                'status_code' => $response['status_code'],
            ]);
        }
    }

    /**
     * Get modifier group ID from a line item
     */
    private function extractModifierGroupId(array $line): array
    {
        $modifierGroupIds = [];
        if (isset($line['modifierProducts'])) {
            foreach ($line['modifierProducts'] as $modifier) {
                if (isset($modifier['modifierGroupId']) ) {
                    $modifierGroupIds[] = $modifier['modifierGroupId'];
                }
            }
        }

        return  $modifierGroupIds;
    }
}
