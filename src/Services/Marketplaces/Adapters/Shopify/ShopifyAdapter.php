<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Exceptions\StaleInventoryException;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\CustomerSyncAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\FulfillmentAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\InventorySyncAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\MarketplaceAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\ProductSyncAdapter;

/**
 * Shopify Admin API adapter.
 *
 * Implements the original orders-only MarketplaceAdapter contract plus the four
 * capability interfaces, so callers can ask what this connection supports
 * instead of assuming. TrendyolGoYemekAdapter still implements only the base
 * contract and is unaffected.
 *
 * The normalize* methods are pure: they take a raw Shopify payload and return
 * our shape, touching neither the database nor the network. That is what makes
 * them testable against captured fixtures without a live shop, and it is where
 * the sync fingerprint is computed.
 */
class ShopifyAdapter implements CustomerSyncAdapter, FulfillmentAdapter, InventorySyncAdapter, MarketplaceAdapter, ProductSyncAdapter
{
    /**
     * Shopify's error code when a compare-and-swap inventory write loses a race.
     */
    private const STALE_CODE = 'CHANGE_FROM_QUANTITY_STALE';

    private Providers $provider;

    private ShopifyClient $client;

    public function __construct(Providers $provider)
    {
        $this->provider = $provider;
        $this->client = new ShopifyClient($provider);
    }

    public function getClient(): ShopifyClient
    {
        return $this->client;
    }

    // -------------------------------------------------------------------------
    // MarketplaceAdapter
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $credentials  Unused: credentials live on the provider row.
     */
    public function authenticate(array $credentials = []): bool
    {
        return $this->client->authenticate();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrders(Carbon|\Carbon\Carbon $since): array
    {
        return $this->collect(
            ShopifyGraphQL::ORDERS_DELTA,
            ['query' => ShopifyGraphQL::updatedSince($since)],
            'orders'
        );
    }

    /**
     * Push a status change back to Shopify.
     *
     * Shopify has no single "set order status" call: fulfilment and cancellation
     * are different mutations against different objects, so the normalised
     * status is mapped onto whichever one applies.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        $order = Orders::withoutGlobalScope(AuthorizationScope::class)
            ->where('external_order_id', $orderId)
            ->where('marketplace_provider_id', $this->provider->id)
            ->first();

        if (! $order) {
            Log::warning(__METHOD__.' - no local order for external id', [
                'provider_id' => $this->provider->id,
                'external_order_id' => $orderId,
            ]);

            return false;
        }

        return match (strtolower($status)) {
            'shipped', 'dispatched', 'fulfilled', 'delivered' => $this->createFulfillment($order),
            'cancelled', 'canceled' => $this->cancelOrder($order),
            'refunded' => $this->refundOrder($order),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $rawOrder
     * @return array<string, mixed>
     */
    public function normalizeOrderData(array $rawOrder): array
    {
        $lineItems = [];

        foreach (data_get($rawOrder, 'lineItems.edges', []) as $edge) {
            $node = $edge['node'] ?? [];

            $lineItems[] = [
                'external_line_id' => data_get($node, 'id'),
                'external_variant_id' => data_get($node, 'variant.id'),
                'sku' => data_get($node, 'sku') ?: data_get($node, 'variant.sku'),
                'name' => data_get($node, 'title'),
                'quantity' => (int) data_get($node, 'quantity', 0),
                'price_per_item' => (float) data_get($node, 'originalUnitPriceSet.shopMoney.amount', 0),
                'total_price' => (float) data_get($node, 'discountedTotalSet.shopMoney.amount', 0),
                'item_data' => $node,
            ];
        }

        $customer = data_get($rawOrder, 'customer');

        return [
            'external_id' => data_get($rawOrder, 'id'),
            'external_order_id' => data_get($rawOrder, 'id'),
            'external_order_number' => data_get($rawOrder, 'name'),
            'external_updated_at' => $this->parseDate(data_get($rawOrder, 'updatedAt')),
            'status' => $this->normalizeStatus($rawOrder),
            'ordered_at' => $this->parseDate(data_get($rawOrder, 'createdAt')),
            'cancelled_at' => $this->parseDate(data_get($rawOrder, 'cancelledAt')),
            'customer_note' => data_get($rawOrder, 'note'),

            // Shop currency: what the existing scalar columns have always meant.
            'subtotal_amount' => (float) data_get($rawOrder, 'currentSubtotalPriceSet.shopMoney.amount', 0),
            'tax_amount' => (float) data_get($rawOrder, 'totalTaxSet.shopMoney.amount', 0),
            'discount_amount' => (float) data_get($rawOrder, 'totalDiscountsSet.shopMoney.amount', 0),
            'delivery_fee' => (float) data_get($rawOrder, 'totalShippingPriceSet.shopMoney.amount', 0),
            'total_amount' => (float) data_get($rawOrder, 'currentTotalPriceSet.shopMoney.amount', 0),
            'shop_currency_code' => data_get($rawOrder, 'currentTotalPriceSet.shopMoney.currencyCode'),

            // Presentment: what the buyer was actually charged. Reading only the
            // shop-currency scalar is what makes integrations misreport revenue
            // on international orders.
            'presentment_total_amount' => (float) data_get($rawOrder, 'currentTotalPriceSet.presentmentMoney.amount', 0),
            'presentment_currency_code' => data_get($rawOrder, 'currentTotalPriceSet.presentmentMoney.currencyCode'),

            'customer' => $customer ? $this->normalizeCustomerData($customer) : null,
            'delivery_address' => data_get($rawOrder, 'shippingAddress'),
            'billing_address' => data_get($rawOrder, 'billingAddress'),
            'line_items' => $lineItems,
            'raw_order_data' => $rawOrder,
            'sync_hash' => $this->fingerprint($rawOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookConfig(): array
    {
        return [
            'security' => 'hmac_sha256',
            'signature_header' => 'X-Shopify-Hmac-SHA256',
            'event_id_header' => 'X-Shopify-Webhook-Id',
            'triggered_at_header' => 'X-Shopify-Triggered-At',
            'shop_header' => 'X-Shopify-Shop-Domain',
            // Shopify allows a 1s connect and 5s total budget, and deletes the
            // subscription after 8 consecutive failures, so the endpoint must
            // persist and return rather than process inline.
            'response_budget_seconds' => 5,
            'auto_delete_after_failures' => 8,
            'supported_events' => self::WEBHOOK_TOPICS,
            'compliance_events' => self::COMPLIANCE_TOPICS,
        ];
    }

    /**
     * Topics this integration subscribes to.
     */
    public const WEBHOOK_TOPICS = [
        'PRODUCTS_CREATE', 'PRODUCTS_UPDATE', 'PRODUCTS_DELETE',
        'INVENTORY_LEVELS_CONNECT', 'INVENTORY_LEVELS_UPDATE', 'INVENTORY_LEVELS_DISCONNECT',
        'INVENTORY_ITEMS_CREATE', 'INVENTORY_ITEMS_UPDATE', 'INVENTORY_ITEMS_DELETE',
        'ORDERS_CREATE', 'ORDERS_UPDATED', 'ORDERS_PAID', 'ORDERS_CANCELLED',
        'ORDERS_FULFILLED', 'ORDERS_DELETE',
        'REFUNDS_CREATE',
        'FULFILLMENTS_CREATE', 'FULFILLMENTS_UPDATE',
        'CUSTOMERS_CREATE', 'CUSTOMERS_UPDATE', 'CUSTOMERS_DELETE',
        'LOCATIONS_CREATE', 'LOCATIONS_UPDATE', 'LOCATIONS_DEACTIVATE',
        'APP_UNINSTALLED', 'APP_SCOPES_UPDATE',
    ];

    /**
     * Mandatory privacy topics. These are declared in shopify.app.toml rather
     * than registered through the Admin API, but the endpoint must answer them.
     */
    public const COMPLIANCE_TOPICS = [
        'customers/data_request',
        'customers/redact',
        'shop/redact',
    ];

    // -------------------------------------------------------------------------
    // ProductSyncAdapter
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(Carbon $since): array
    {
        return $this->collect(
            ShopifyGraphQL::PRODUCTS_DELTA,
            ['query' => ShopifyGraphQL::updatedSince($since)],
            'products'
        );
    }

    /**
     * @param  array<string, mixed>  $rawProduct
     * @return array<string, mixed>
     */
    public function normalizeProductData(array $rawProduct): array
    {
        // A product past the variant page size needs its own walk; syncing the
        // first 250 and calling it done would silently truncate the catalogue.
        if (data_get($rawProduct, 'variants.pageInfo.hasNextPage')) {
            Log::warning(__METHOD__.' - product has more variants than one page', [
                'provider_id' => $this->provider->id,
                'external_id' => data_get($rawProduct, 'id'),
            ]);
        }

        $variants = [];

        foreach (data_get($rawProduct, 'variants.edges', []) as $edge) {
            $node = $edge['node'] ?? [];

            $options = [];

            foreach (data_get($node, 'selectedOptions', []) as $option) {
                $options[(string) data_get($option, 'name')] = data_get($option, 'value');
            }

            $variants[] = [
                // The GID is the join key. SKU is a hint only: Shopify does not
                // enforce SKU uniqueness, so matching on it corrupts catalogues.
                'external_id' => data_get($node, 'id'),
                'external_inventory_item_id' => data_get($node, 'inventoryItem.id'),
                'name' => data_get($node, 'title'),
                'sku' => data_get($node, 'sku'),
                'price' => (float) data_get($node, 'price', 0),
                'compare_at_price' => data_get($node, 'compareAtPrice'),
                'quantity_in_inventory' => (int) data_get($node, 'inventoryQuantity', 0),
                'is_inventory_tracked' => (bool) data_get($node, 'inventoryItem.tracked', false),
                'options' => $options,
                'external_updated_at' => $this->parseDate(data_get($node, 'updatedAt')),
                'sync_hash' => $this->fingerprint($node),
            ];
        }

        $images = [];

        foreach (data_get($rawProduct, 'images.edges', []) as $edge) {
            $url = data_get($edge, 'node.url');

            if ($url) {
                $images[] = ['url' => $url, 'alt' => data_get($edge, 'node.altText')];
            }
        }

        return [
            'external_id' => data_get($rawProduct, 'id'),
            'external_updated_at' => $this->parseDate(data_get($rawProduct, 'updatedAt')),
            'name' => data_get($rawProduct, 'title'),
            'slug' => data_get($rawProduct, 'handle'),
            'description' => data_get($rawProduct, 'descriptionHtml'),
            'category_name' => data_get($rawProduct, 'productType'),
            'vendor' => data_get($rawProduct, 'vendor'),
            'is_active' => data_get($rawProduct, 'status') === 'ACTIVE',
            'tags' => data_get($rawProduct, 'tags', []),
            'images' => $images,
            'variants' => $variants,
            'raw' => $rawProduct,
            'sync_hash' => $this->fingerprint($rawProduct),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pushProduct(Products $product): array
    {
        $this->assertPushAllowed('products');

        throw new \LogicException(
            'Shopify product push is not enabled yet. It is gated behind an approved dry-run; '
            .'see docs/marketplace/shopify-two-way-sync.md.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pushCatalog(ProductCatalogs $catalog): array
    {
        $this->assertPushAllowed('products');

        throw new \LogicException(
            'Shopify catalog push is not enabled yet. It is gated behind an approved dry-run; '
            .'see docs/marketplace/shopify-two-way-sync.md.'
        );
    }

    // -------------------------------------------------------------------------
    // InventorySyncAdapter
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchInventoryLevels(Carbon $since): array
    {
        $locationId = $this->locationId();

        $levels = $this->collect(
            ShopifyGraphQL::INVENTORY_DELTA,
            ['locationId' => $locationId],
            'location.inventoryLevels'
        );

        $normalized = [];

        foreach ($levels as $level) {
            $updatedAt = $this->parseDate(data_get($level, 'updatedAt'));

            // The query cannot filter by updatedAt, so the delta is applied here.
            if ($updatedAt !== null && $updatedAt->lessThan($since)) {
                continue;
            }

            $quantities = [];

            foreach (data_get($level, 'quantities', []) as $quantity) {
                $quantities[(string) data_get($quantity, 'name')] = (int) data_get($quantity, 'quantity', 0);
            }

            $normalized[] = [
                'external_inventory_item_id' => data_get($level, 'item.id'),
                'external_variant_id' => data_get($level, 'item.variant.id'),
                'sku' => data_get($level, 'item.sku'),
                'available' => $quantities['available'] ?? 0,
                'on_hand' => $quantities['on_hand'] ?? null,
                'committed' => $quantities['committed'] ?? null,
                'external_updated_at' => $updatedAt,
                'location_id' => $locationId,
            ];
        }

        return $normalized;
    }

    /**
     * Set stock on Shopify using compare-and-swap.
     *
     * @throws StaleInventoryException When the remote quantity moved underneath us.
     */
    /**
     * @param  string|null  $operationRef  Distinguishes genuinely separate pushes
     *                                     that happen to move the same numbers.
     */
    public function pushInventoryLevel(ProductCatalogs $catalog, int $quantity, ?int $changeFrom = null, ?string $operationRef = null): bool
    {
        $this->assertPushAllowed('inventory');

        $inventoryItemId = $this->inventoryItemIdFor($catalog);

        if ($inventoryItemId === null) {
            Log::warning(__METHOD__.' - catalog has no mapped Shopify inventory item', [
                'provider_id' => $this->provider->id,
                'catalog_id' => $catalog->id,
            ]);

            return false;
        }

        $change = [
            'inventoryItemId' => $inventoryItemId,
            'locationId' => $this->locationId(),
            'quantity' => $quantity,
        ];

        // changeFromQuantity is the compare-and-swap guard that replaced the
        // removed compareQuantity field. Omitting it forces the write, which is
        // only acceptable on an explicit operator override — never as a silent
        // fallback for a read we failed to do, because that is exactly how one
        // channel overwrites another channel's sale.
        if ($changeFrom !== null) {
            $change['changeFromQuantity'] = $changeFrom;
        }

        $input = [
            'name' => 'available',
            'reason' => 'correction',
            'quantities' => [$change],
        ];

        try {
            $this->client->mutate(
                ShopifyGraphQL::INVENTORY_SET,
                ['input' => $input],
                /*
                 * The key must identify the *operation*, not just the numbers.
                 * Shopify honours a repeated key by replaying the original
                 * response without re-running the mutation, so a key derived
                 * only from (catalog, quantity, changeFrom) makes the second
                 * "set 57 from 50" a silent no-op — which is exactly what
                 * happens when stock oscillates (sell seven, restock seven)
                 * inside the key-retention window, and the drift is invisible
                 * because the call reports success.
                 *
                 * $operationRef is the observed remote state this push is
                 * based on, so a retry of the same decision reuses the key
                 * while a later, genuinely different push does not.
                 */
                $this->idempotencyKey(
                    'inventory',
                    (string) $catalog->id,
                    (string) $quantity,
                    (string) $changeFrom,
                    (string) $operationRef
                )
            );
        } catch (ShopifyUserException $e) {
            if ($e->hasCode(self::STALE_CODE)) {
                throw new StaleInventoryException(
                    'Shopify stock for catalog '.$catalog->id.' changed during the update.',
                    null,
                    0,
                    $e
                );
            }

            throw $e;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // CustomerSyncAdapter
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(Carbon $since): array
    {
        return $this->collect(
            ShopifyGraphQL::CUSTOMERS_DELTA,
            ['query' => ShopifyGraphQL::updatedSince($since)],
            'customers'
        );
    }

    /**
     * @param  array<string, mixed>  $rawCustomer
     * @return array<string, mixed>
     */
    public function normalizeCustomerData(array $rawCustomer): array
    {
        $email = data_get($rawCustomer, 'email');
        $email = is_string($email) ? trim($email) : null;

        return [
            'external_id' => data_get($rawCustomer, 'id'),
            'external_updated_at' => $this->parseDate(data_get($rawCustomer, 'updatedAt')),
            // Nullable on purpose: a guest checkout has no customer record and
            // must not produce an iam_users row.
            'email' => $email !== '' ? $email : null,
            'email_normalized' => $email ? mb_strtolower($email) : null,
            'phone' => data_get($rawCustomer, 'phone'),
            'first_name' => data_get($rawCustomer, 'firstName'),
            'last_name' => data_get($rawCustomer, 'lastName'),
            'orders_count' => (int) data_get($rawCustomer, 'numberOfOrders', 0),
            'total_spent' => (float) data_get($rawCustomer, 'amountSpent.amount', 0),
            'currency_code' => data_get($rawCustomer, 'amountSpent.currencyCode'),
            'default_address' => data_get($rawCustomer, 'defaultAddress'),
            'country_code' => data_get($rawCustomer, 'defaultAddress.countryCodeV2'),
            'tags' => data_get($rawCustomer, 'tags', []),
            'raw' => $rawCustomer,
            'sync_hash' => $this->fingerprint($rawCustomer),
        ];
    }

    // -------------------------------------------------------------------------
    // FulfillmentAdapter
    // -------------------------------------------------------------------------

    public function createFulfillment(Orders $order, array $lines = []): bool
    {
        $this->assertPushAllowed('fulfillment');

        $fulfillmentOrders = $this->client->query(
            ShopifyGraphQL::FULFILLMENT_ORDERS,
            ['orderId' => $order->external_order_id]
        );

        $edges = data_get($fulfillmentOrders, 'order.fulfillmentOrders.edges', []);

        if ($edges === []) {
            Log::warning(__METHOD__.' - no fulfillment orders to fulfil', [
                'provider_id' => $this->provider->id,
                'order_id' => $order->id,
            ]);

            return false;
        }

        $lineItemsByFulfillmentOrder = [];

        foreach ($edges as $edge) {
            $node = $edge['node'] ?? [];

            if (in_array(data_get($node, 'status'), ['CLOSED', 'CANCELLED'], true)) {
                continue;
            }

            $lineItemsByFulfillmentOrder[] = ['fulfillmentOrderId' => data_get($node, 'id')];
        }

        if ($lineItemsByFulfillmentOrder === []) {
            return false;
        }

        $this->client->mutate(
            ShopifyGraphQL::FULFILLMENT_CREATE,
            ['fulfillment' => ['lineItemsByFulfillmentOrder' => $lineItemsByFulfillmentOrder, 'notifyCustomer' => true]],
            $this->idempotencyKey('fulfillment', (string) $order->id, (string) $order->updated_at)
        );

        return true;
    }

    public function cancelOrder(Orders $order, string $reason = ''): bool
    {
        $this->assertPushAllowed('fulfillment');

        $this->client->mutate(
            ShopifyGraphQL::ORDER_CANCEL,
            [
                'orderId' => $order->external_order_id,
                'reason' => $this->cancelReason($reason),
                'refund' => false,
                'restock' => true,
                'notifyCustomer' => true,
            ],
            $this->idempotencyKey('cancel', (string) $order->id)
        );

        return true;
    }

    public function refundOrder(Orders $order, array $lines = []): bool
    {
        $this->assertPushAllowed('fulfillment');

        // The key deliberately excludes anything volatile: a retry of the same
        // refund must reuse it, or the customer is paid twice.
        $key = $this->idempotencyKey('refund', (string) $order->id, md5(json_encode($lines) ?: ''));

        $this->client->mutate(
            ShopifyGraphQL::REFUND_CREATE,
            ['input' => ['orderId' => $order->external_order_id, 'note' => 'Refunded from PlusClouds marketplace']],
            $key
        );

        return true;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Drain a paginated connection into an array.
     *
     * @param  array<string, mixed>  $variables
     * @return array<int, array<string, mixed>>
     */
    private function collect(string $query, array $variables, string $path): array
    {
        $out = [];

        foreach ($this->client->paginate($query, $variables, $path) as $node) {
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Deterministic fingerprint of a remote payload.
     *
     * This is the echo-loop guard: after we write to Shopify, Shopify sends the
     * change straight back as a webhook, and Shopify attributes no actor, so
     * comparing the fingerprint is the only way to recognise our own write.
     * Keys are sorted so an ordering difference is not mistaken for a change.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fingerprint(array $payload): string
    {
        $normalized = $this->sortRecursive($payload);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortRecursive(array $value): array
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->sortRecursive($v);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * Build a stable idempotency key.
     *
     * From API 2026-04 Shopify requires one on inventory and refund mutations,
     * and rejects the call at runtime when it is missing. Deriving it from the
     * operation instead of randomly is what makes a retry safe.
     */
    private function idempotencyKey(string $operation, string ...$parts): string
    {
        return substr(hash('sha256', implode(':', array_merge(
            [(string) $this->provider->id, $operation],
            $parts
        ))), 0, 36);
    }

    /**
     * The inventory location this connection writes to.
     */
    private function locationId(): string
    {
        $config = $this->provider->getApiConfigArray();

        $locationId = $config['location_id'] ?? null;

        if (! is_string($locationId) || $locationId === '') {
            throw new \RuntimeException(
                'Shopify provider '.$this->provider->uuid.' has no location_id in api_config. '
                .'Multi-location fan-out is not supported; pin one location per connection.'
            );
        }

        return $locationId;
    }

    /**
     * Resolve the Shopify InventoryItem GID for a local catalog entry.
     */
    private function inventoryItemIdFor(ProductCatalogs $catalog): ?string
    {
        $mapping = ProductCatalogMappings::withoutGlobalScope(
            AuthorizationScope::class
        )
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('marketplace_product_catalog_id', $catalog->id)
            ->first();

        return $mapping?->external_inventory_item_id;
    }

    /**
     * Refuse to write when the provider's policy does not allow it.
     *
     * Defaults are pull-only, so a misconfigured or half-configured connection
     * cannot start overwriting a merchant's live data.
     */
    private function assertPushAllowed(string $entity): void
    {
        if (! $this->provider->canPush($entity)) {
            throw new \LogicException(sprintf(
                'Provider %s is not configured to push %s (sync_policy.%s.direction).',
                $this->provider->uuid,
                $entity,
                $entity
            ));
        }
    }

    /**
     * Map a free-text reason onto Shopify's OrderCancelReason enum.
     */
    private function cancelReason(string $reason): string
    {
        return match (strtoupper(trim($reason))) {
            'CUSTOMER' => 'CUSTOMER',
            'FRAUD' => 'FRAUD',
            'INVENTORY' => 'INVENTORY',
            'DECLINED' => 'DECLINED',
            'STAFF' => 'STAFF',
            default => 'OTHER',
        };
    }

    /**
     * Normalise a Shopify order into our vocabulary.
     *
     * Deliberately coarse: per-provider nuance belongs in marketplace_status_mappings,
     * which the service layer applies on top of this.
     *
     * @param  array<string, mixed>  $rawOrder
     */
    private function normalizeStatus(array $rawOrder): string
    {
        if (data_get($rawOrder, 'cancelledAt')) {
            return 'cancelled';
        }

        return match (data_get($rawOrder, 'displayFulfillmentStatus')) {
            'FULFILLED' => 'delivered',
            'PARTIALLY_FULFILLED' => 'dispatched',
            'IN_PROGRESS' => 'prepared',
            default => data_get($rawOrder, 'displayFinancialStatus') === 'PAID'
                ? 'accepted'
                : 'new',
        };
    }

    /**
     * Parse a Shopify timestamp into an app-timezone Carbon.
     *
     * The timezone conversion is not cosmetic. Shopify sends UTC ("...Z"), and
     * Laravel persists a datetime as naive wall-clock text, which Postgres then
     * reads in the connection's timezone. Handing the model a UTC Carbon while
     * the app runs on Europe/Istanbul therefore stored every external timestamp
     * shifted by the offset — orders dated hours before they were placed, and
     * ordering guards comparing against a skewed mapping timestamp. Converting
     * here, at the one place external dates enter the system, keeps every
     * downstream write and comparison on the same clock as created_at.
     */
    /**
     * Public wrapper so the webhook processor parses Shopify dates by exactly
     * the same rules as the pull path, timezone conversion included.
     */
    public function parseExternalDate(mixed $value): ?Carbon
    {
        return $this->parseDate($value);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
