<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ideasoft;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\InventorySyncAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\MarketplaceAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\ProductSyncAdapter;

/**
 * Ideasoft merchant API adapter.
 *
 * Panel/Leo is the source of truth for the product catalogue; Ideasoft owns
 * payment, fulfilment and shipping. This adapter therefore implements the
 * base order-mirroring contract plus the two push-capable interfaces
 * (products, inventory) — deliberately not FulfillmentAdapter, since order
 * status changes are never written back from the panel (see
 * docs/marketplace/PLAN.md in panel-v6.2 for the ownership decision).
 *
 * updateOrderStatus() below is still required by MarketplaceAdapter's base
 * contract; it is a documented no-op here rather than an omission.
 *
 * Push methods (pushProduct/pushCatalog/pushInventoryLevel) are gated behind
 * the same sync_policy.<entity>.direction mechanism Shopify uses
 * (Providers::canPush()), and throw "not enabled yet" the same way
 * ShopifyAdapter::pushProduct() does — see that class for precedent. Pull
 * methods (fetchOrders/fetchProducts/fetchInventoryLevels) delegate to
 * IdeasoftClient, whose request-shaped methods are themselves unimplemented
 * pending confirmed endpoint paths — see IdeasoftClient's docblock.
 */
class IdeasoftAdapter implements InventorySyncAdapter, MarketplaceAdapter, ProductSyncAdapter
{
    private Providers $provider;

    private IdeasoftClient $client;

    public function __construct(Providers $provider)
    {
        $this->provider = $provider;
        $this->client = new IdeasoftClient($provider);
    }

    public function getClient(): IdeasoftClient
    {
        return $this->client;
    }

    // -------------------------------------------------------------------------
    // MarketplaceAdapter
    // -------------------------------------------------------------------------

    public function authenticate(array $credentials = []): bool
    {
        return $this->client->hasStoredToken();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrders(Carbon|\Carbon\Carbon $since): array
    {
        return $this->client->getOrders($since);
    }

    /**
     * Ideasoft owns fulfilment; the panel never writes order status back.
     * Kept as a logged no-op to satisfy the base contract explicitly rather
     * than by omission.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        Log::info(__METHOD__.' - order status push-back is intentionally disabled for Ideasoft', [
            'provider_id' => $this->provider->id,
            'external_order_id' => $orderId,
            'requested_status' => $status,
        ]);

        return false;
    }

    /**
     * Best-effort mapping from a raw Ideasoft order payload to our internal
     * shape. Field names are placeholders pending a confirmed payload sample
     * from apidoc.ideasoft.dev — adjust the data_get() paths below once one
     * is available, the mapping and the sync_hash below deliberately stay
     * pure so this can be re-run against captured fixtures without a live
     * store, matching ShopifyAdapter::normalizeOrderData()'s testability.
     *
     * @param  array<string, mixed>  $rawOrder
     * @return array<string, mixed>
     */
    public function normalizeOrderData(array $rawOrder): array
    {
        return [
            'external_id' => data_get($rawOrder, 'id'),
            'external_order_id' => data_get($rawOrder, 'id'),
            'external_order_number' => data_get($rawOrder, 'orderNumber'),
            'external_updated_at' => $this->parseDate(data_get($rawOrder, 'updatedAt')),
            'status' => (string) data_get($rawOrder, 'status', 'new'),
            'ordered_at' => $this->parseDate(data_get($rawOrder, 'createdAt')),
            'cancelled_at' => $this->parseDate(data_get($rawOrder, 'cancelledAt')),
            'customer_note' => data_get($rawOrder, 'note'),
            'subtotal_amount' => (float) data_get($rawOrder, 'subtotal', 0),
            'tax_amount' => (float) data_get($rawOrder, 'tax', 0),
            'discount_amount' => (float) data_get($rawOrder, 'discount', 0),
            'delivery_fee' => (float) data_get($rawOrder, 'shippingCost', 0),
            'total_amount' => (float) data_get($rawOrder, 'total', 0),
            'delivery_address' => data_get($rawOrder, 'shippingAddress'),
            'billing_address' => data_get($rawOrder, 'billingAddress'),
            'raw_order_data' => $rawOrder,
        ];
    }

    /**
     * Ideasoft's webhook support is unconfirmed — their public help doc only
     * describes REST/XML data access, not push notifications. Returning an
     * explicitly empty/unsupported config rather than guessing Shopify-style
     * HMAC details that would not apply here.
     *
     * @return array<string, mixed>
     */
    public function getWebhookConfig(): array
    {
        return [
            'supported' => false,
            'supported_events' => [],
            'note' => 'Webhook support unconfirmed for Ideasoft; verify against apidoc.ideasoft.dev before relying on this.',
        ];
    }

    // -------------------------------------------------------------------------
    // ProductSyncAdapter
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(Carbon $since): array
    {
        throw new \LogicException(
            'Ideasoft product fetch is not implemented yet: the exact endpoint path is unconfirmed. '
            .'See IdeasoftClient class docblock.'
        );
    }

    /**
     * @param  array<string, mixed>  $rawProduct
     * @return array<string, mixed>
     */
    public function normalizeProductData(array $rawProduct): array
    {
        return [
            'external_id' => data_get($rawProduct, 'id'),
            'external_updated_at' => $this->parseDate(data_get($rawProduct, 'updatedAt')),
            'name' => data_get($rawProduct, 'name'),
            'sku' => data_get($rawProduct, 'sku'),
            'price' => (float) data_get($rawProduct, 'price', 0),
            'quantity_in_inventory' => (int) data_get($rawProduct, 'stock', 0),
            'raw' => $rawProduct,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pushProduct(Products $product): array
    {
        $this->assertPushAllowed('products');

        throw new \LogicException(
            'Ideasoft product push is not enabled yet. It is gated behind confirming the write endpoint; '
            .'see IdeasoftClient class docblock.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pushCatalog(ProductCatalogs $catalog): array
    {
        $this->assertPushAllowed('products');

        throw new \LogicException(
            'Ideasoft catalog push is not enabled yet. It is gated behind confirming the write endpoint; '
            .'see IdeasoftClient class docblock.'
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
        throw new \LogicException(
            'Ideasoft inventory fetch is not implemented yet: the exact endpoint path is unconfirmed. '
            .'See IdeasoftClient class docblock.'
        );
    }

    public function pushInventoryLevel(ProductCatalogs $catalog, int $quantity, ?int $changeFrom = null, ?string $operationRef = null): bool
    {
        $this->assertPushAllowed('inventory');

        if ($catalog->sku === null || $catalog->sku === '') {
            Log::warning(__METHOD__.' - catalog has no SKU to push against Ideasoft', [
                'provider_id' => $this->provider->id,
                'catalog_id' => $catalog->id,
            ]);

            return false;
        }

        return $this->client->pushStock($catalog->sku, $quantity);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    /**
     * Refuse to write when the provider's policy does not allow it. Mirrors
     * ShopifyAdapter::assertPushAllowed() — defaults are pull-only, so a
     * misconfigured connection cannot start overwriting a merchant's live
     * catalogue.
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
