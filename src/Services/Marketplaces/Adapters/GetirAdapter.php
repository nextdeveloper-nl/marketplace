<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Helpers\MappingExternalProduct;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\OrderItems;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Services\OrdersService;
use NextDeveloper\Marketplace\Services\OrderItemsService;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use Throwable;

/**
 * Getir marketplace adapter (INITIAL IMPLEMENTATION)
 * This adapter implements the MarketplaceAdapter interface
 * and provides methods to interact with the Getir Food API.
 * It handles authentication, fetching orders, updating order statuses,
 * and normalizing order data according to our internal schema.
 *
 */
class GetirAdapter implements MarketplaceAdapter
{
    private const DEFAULT_TIMEOUT = 60;

    // Assumed status mapping from internal status -> Getir API status values
    private const STATUS_MAP = [
        'picking' => 'PREPARING',
        'shipped' => 'ON_THE_WAY',
        'delivered' => 'DELIVERED',
        'cancelled' => 'CANCELLED',
    ];

    private array $config;
    private Providers $provider;
    private string $baseUri;
    private int $timeout;
    private ?string $accessToken = null;
    private ?Carbon $tokenExpiresAt = null; // Getir login response does not provide expiry; kept for future use
    private ?string $restaurantId = null;

    public function __construct(array $config, Providers $provider)
    {
        $this->config = $config;
        $this->provider = $provider;
        $this->baseUri = rtrim($config['base_url'] ?? 'https://food-external-api-gateway.development.getirapi.com', '/');
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * Authenticate (retrieve bearer token) using Getir Food login endpoint.
     * Endpoint spec provided:
     * POST /auth/login { appSecretKey, restaurantSecretKey } => { restaurantId, token }
     */
    public function authenticate(array $credentials): bool
    {
        try {
            $appSecretKey = (string) ($credentials['app_secret_key'] ?? $credentials['appSecretKey'] ?? $this->config['app_secret_key'] ?? $this->config['appSecretKey'] ?? '');
            $restaurantSecretKey = (string) ($credentials['restaurant_secret_key'] ?? $credentials['restaurantSecretKey'] ?? $this->config['restaurant_secret_key'] ?? $this->config['restaurantSecretKey'] ?? '');

            if (!$appSecretKey || !$restaurantSecretKey) {
                Log::warning(__METHOD__ . ' - Missing appSecretKey or restaurantSecretKey.');
                return false;
            }

            $endpoint = '/auth/login';
            $payload = [
                'appSecretKey' => $appSecretKey,
                'restaurantSecretKey' => $restaurantSecretKey,
            ];

            $response = $this->curlRequest('POST', $endpoint, ['accept' => 'application/json'], $payload);

            dd($response);

            if ($response['status_code'] !== 200) {
                Log::error(__METHOD__ . ' - Authentication failed', [
                    'status_code' => $response['status_code'],
                    'body' => $response['body'],
                ]);
                return false;
            }

            $data = json_decode($response['body'], true) ?: [];
            $this->accessToken = $data['token'] ?? null;
            $this->restaurantId = $data['restaurantId'] ?? null;
            // No expiry in spec; keep token until failure, optionally set a soft TTL (e.g., 6h)
            $this->tokenExpiresAt = Carbon::now()->addHours(6);

            if (!$this->accessToken) {
                Log::error(__METHOD__ . ' - No token in response');
                return false;
            }

            Log::info(__METHOD__ . ' - Authentication successful', [
                'restaurant_id' => $this->restaurantId,
                'soft_expires_at' => $this->tokenExpiresAt?->toIso8601String(),
            ]);
            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' - Auth error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Ensure valid token
     */
    private function ensureAuthenticated(): bool
    {
        // If token exists and soft TTL not passed, reuse; otherwise re-auth
        if ($this->accessToken && ($this->tokenExpiresAt === null || $this->tokenExpiresAt->isFuture())) {
            return true;
        }
        return $this->authenticate([]);
    }

    /**
     * Fetch orders for a date range (Getir provided endpoint: GET /food-orders/report)
     * Query params:
     *  - startDate (YYYY-MM-DD)
     *  - endDate (YYYY-MM-DD)
     *  - restaurantIds (comma separated)
     * Header: token: <token>
     */
    public function fetchOrders(\Illuminate\Support\Carbon|Carbon $since): array
    {
        try {
            if (!$this->ensureAuthenticated()) {
                return [];
            }

            $startDate = $since->copy()->startOfDay()->format('Y-m-d');
            // If request spans multiple days we could extend; for now single day or until today
            $endDate = Carbon::now()->format('Y-m-d');
            // If caller wants only that day keep same
            if ($since->isSameDay(Carbon::now())) {
                $endDate = $startDate;
            }

            $restaurantIds = $this->restaurantId ?? ($this->config['restaurant_ids'] ?? $this->config['restaurantId'] ?? '');
            if (is_array($restaurantIds)) {
                $restaurantIds = implode(',', $restaurantIds);
            }
            if (!$restaurantIds) {
                Log::warning(__METHOD__ . ' - Missing restaurantIds; aborting fetch');
                return [];
            }

            $query = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'restaurantIds' => $restaurantIds,
            ];

            $endpoint = '/food-orders/report';
            $headers = [
                'token' => $this->accessToken,
                'accept' => 'application/json'
            ];

            $response = $this->curlRequest('GET', $endpoint, $headers, [], $query);
            if ($response['status_code'] !== 200) {
                Log::error(__METHOD__ . ' - Failed to fetch orders', [
                    'status_code' => $response['status_code'],
                    'body' => $response['body'],
                    'query' => $query,
                ]);
                return [];
            }

            $data = json_decode($response['body'], true) ?: [];
            // Without schema we attempt common keys
            $orders = $data['orders'] ?? $data['items'] ?? $data['data'] ?? $data;
            if (!is_array($orders)) {
                Log::warning(__METHOD__ . ' - Unexpected orders format', ['decoded' => $data]);
                return [];
            }

            Log::info(__METHOD__ . ' - Orders fetched', [
                'count' => count($orders),
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);
            return $orders;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' - Error fetching orders', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Update order status using Getir specific endpoints:
     *  POST /food-orders/{id}/verify
     *  POST /food-orders/{id}/prepare
     *  POST /food-orders/{id}/deliver
     *  POST /food-orders/{id}/cancel
     * Internal statuses expected: picking -> prepare, shipped -> deliver (or future: out_for_delivery), delivered -> deliver, cancelled -> cancel.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            if (!$this->ensureAuthenticated()) {
                return false;
            }

            $action = match ($status) {
                'picking' => 'prepare',   // or 'verify' if business flow requires verifying first
                'shipped' => 'deliver',   // no explicit shipped endpoint; deliver marks completion
                'delivered' => 'deliver',
                'cancelled' => 'cancel',
                'verify' => 'verify',    // allow manual verify
                default => null,
            };

            if (!$action) {
                Log::warning(__METHOD__ . ' - Unsupported status for Getir', [
                    'status' => $status,
                    'order_id' => $orderId,
                ]);
                return false;
            }

            $endpoint = "/food-orders/{$orderId}/{$action}";
            $headers = [
                'token' => $this->accessToken,
                'accept' => 'application/json'
            ];

            $response = $this->curlRequest('POST', $endpoint, $headers, []);
            $success = $response['status_code'] === 200 && ($data = json_decode($response['body'], true)) && ($data['result'] ?? false) === true;

            if ($success) {
                Log::info(__METHOD__ . ' - Order action successful', [
                    'order_id' => $orderId,
                    'action' => $action,
                ]);
            } else {
                Log::error(__METHOD__ . ' - Order action failed', [
                    'order_id' => $orderId,
                    'action' => $action,
                    'status_code' => $response['status_code'],
                    'body' => $response['body'],
                ]);
            }
            return $success;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' - Error updating order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Normalize a single raw order (fields need confirmation against Swagger)
     */
    public function normalizeOrderData(array $rawOrder): array
    {
        try {
            return [
                'external_order_id' => $rawOrder['id'] ?? $rawOrder['order_id'] ?? null,
                'external_order_number' => $rawOrder['code'] ?? $rawOrder['order_number'] ?? null,
                // Map current external status to internal; placeholder uses raw status field if available
                'status' => MappingExternalProduct::mapOrderStatus($rawOrder['status'] ?? null, $this->provider),
                'ordered_at' => $this->parseDate($rawOrder['created_at'] ?? null),
                'delivered_at' => $this->parseDate($rawOrder['delivered_at'] ?? null),
                'customer_data' => $this->normalizeCustomer($rawOrder['customer'] ?? []),
                'delivery_address' => $this->buildAddress($rawOrder['delivery_address'] ?? []),
                'subtotal_amount' => (float) ($rawOrder['subtotal'] ?? $rawOrder['total_before_discount'] ?? 0),
                'discount_amount' => (float) ($rawOrder['discount_total'] ?? 0),
                'total_amount' => (float) ($rawOrder['total'] ?? $rawOrder['grand_total'] ?? 0),
                'order_type' => 'delivery',
                'delivery_method' => 'platform_delivery',
                'estimated_delivery_time' => $this->parseDate($rawOrder['estimated_delivery_time'] ?? null),
                'raw_order_data' => $rawOrder,
                'last_sync_at' => now()->toISOString(),
                'customer_note' => $rawOrder['note'] ?? $rawOrder['customer_note'] ?? null,
                'items' => $this->normalizeItems($rawOrder['items'] ?? $rawOrder['lines'] ?? []),
                'order_code' => $rawOrder['code'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' - Error normalizing order', [
                'error' => $e->getMessage(),
                'raw' => $rawOrder,
            ]);
            return [
                'external_order_id' => (string) ($rawOrder['id'] ?? uniqid('getir_')),
                'status' => $rawOrder['status'] ?? 'unknown',
                'raw_order_data' => $rawOrder,
                'sync_status' => 'error',
                'sync_error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Webhook configuration (assumed capabilities)
     */
    public function getWebhookConfig(): array
    {
        return [
            'supported_events' => [
                'order.created' => ['payload_format' => 'json'],
                'order.status_changed' => ['payload_format' => 'json'],
            ],
            'security' => [
                'authentication_method' => 'bearer_token',
                'requires_ssl' => true,
            ],
            'retry_policy' => [
                'max_attempts' => 3,
                'retry_delay' => 300,
                'exponential_backoff' => true,
            ],
            'timeout' => 30,
            'notes' => 'Values are placeholders until confirmed with Getir API docs.'
        ];
    }

    /* -------------------- Internal Helpers -------------------- */

    private function parseDate(?string $value): ?string
    {
        if (!$value)
            return null;
        try {
            $dt = Carbon::parse($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            Log::warning(__METHOD__ . ' - Failed to parse date', [
                'value' => $value,
            ]);
            return null;
        }
    }

    private function normalizeCustomer(array $c): array
    {
        return [
            'id' => $c['id'] ?? null,
            'name' => $c['first_name'] ?? $c['name'] ?? 'Unknown Customer',
            'lastname' => $c['last_name'] ?? '',
            'email' => $c['email'] ?? 'unknown@email.com',
        ];
    }

    private function buildAddress(array $a): string
    {
        if (empty($a))
            return '';
        $parts = array_filter([
            $a['address_line_1'] ?? $a['line1'] ?? null,
            $a['address_line_2'] ?? $a['line2'] ?? null,
            $a['district'] ?? null,
            $a['city'] ?? null,
            $a['postal_code'] ?? null,
            $a['country_code'] ?? null,
            $a['phone'] ?? null,
        ]);
        return implode(', ', $parts);
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = [
                'id' => $item['product_id'] ?? $item['id'] ?? null,
                'name' => $item['name'] ?? $item['title'] ?? null,
                'price_per_item' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                'total_price' => (float) ($item['total_price'] ?? $item['line_total'] ?? $item['price'] ?? 0),
                'external_line_id' => $item['line_id'] ?? $item['id'] ?? null,
                'subItems' => [], // placeholder for modifiers/add-ons
                'removedIngredients' => [], // placeholder
            ];
        }
        return $normalized;
    }

    /* -------- cURL Abstraction (similar to Trendyol adapter) -------- */

    private function curlRequest(string $method, string $endpoint, array $headers = [], array $data = [], array $query = []): array
    {
        $url = $this->buildRequestUrl($endpoint, $query);
        $ch = curl_init();

        $curlHeaders = $this->buildCurlHeaders($headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        try {
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            if ($errno) {
                throw new Exception("cURL error: $error", $errno);
            }

            return [
                'status_code' => $httpCode,
                'body' => $body,
            ];
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' - cURL request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return [
                'status_code' => 0,
                'body' => '',
            ];
        } finally {
            curl_close($ch);
        }
    }

    private function buildRequestUrl(string $endpoint, array $query): string
    {
        $url = $this->baseUri . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    private function buildCurlHeaders(array $headers): array
    {
        $default = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: GetirAdapter/1.0',
        ];
        foreach ($headers as $k => $v) {
            // If header given as numeric array style already contains colon, keep as-is
            if (is_int($k)) {
                $default[] = $v;
            } else {
                $default[] = $k . ': ' . $v;
            }
        }
        return $default;
    }
}
