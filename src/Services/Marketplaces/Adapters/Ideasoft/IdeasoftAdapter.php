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
        $groups = $this->groupVariants($this->client->getProducts());

        // Ideasoft cannot filter by date server-side (checked: an updatedAt filter is ignored), so
        // the delta is applied here. A group is kept when the parent OR any variant changed, so a
        // stock change on one variant still refreshes the whole product.
        return array_values(array_filter($groups, function (array $group) use ($since) {
            foreach ([$group, ...($group['_variants'] ?? [])] as $record) {
                $updated = $this->parseDate(data_get($record, 'updatedAt'));

                if ($updated === null || $updated->greaterThanOrEqualTo($since)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Ideasoft stores a variant as its own product record with `parent` set to the main product
     * (confirmed on a live store: variants 142 and 143 have parent 138), and the parent's
     * stockAmount is the SUM of its variants. Group the flat list so each main product carries its
     * variants under `_variants`, and a variant never shows up as a product of its own.
     *
     * @param  array<int, array<string, mixed>>  $flat
     * @return array<int, array<string, mixed>>
     */
    private function groupVariants(array $flat): array
    {
        $children = [];
        $parents = [];

        foreach ($flat as $record) {
            $parentId = data_get($record, 'parent.id');

            if ($parentId !== null) {
                $children[(string) $parentId][] = $record;
            } else {
                $parents[] = $record;
            }
        }

        foreach ($parents as &$parent) {
            $parent['_variants'] = $children[(string) data_get($parent, 'id')] ?? [];
        }
        unset($parent);

        return $parents;
    }

    /**
     * @param  array<string, mixed>  $rawProduct
     * @return array<string, mixed>
     */
    public function normalizeProductData(array $rawProduct): array
    {
        // Mapping verified against a live store payload (GET /admin-api/products): id (int),
        // name/fullName, sku, barcode (nullable), stockAmount (float), price1, currency.abbr,
        // status (1/0), updatedAt (ISO 8601), images[].originalUrl (protocol-relative "//host/..."),
        // parent (set on variants only).
        $variants = [];

        foreach ($rawProduct['_variants'] ?? [] as $variant) {
            $variants[] = [
                'external_id' => (string) data_get($variant, 'id'),
                'name' => data_get($variant, 'name'),
                'sku' => data_get($variant, 'sku'),
                'barcode' => data_get($variant, 'barcode'),
                'price' => (float) data_get($variant, 'price1', 0),
                'quantity_in_inventory' => $this->stock(data_get($variant, 'stockAmount')),
                'external_updated_at' => $this->parseDate(data_get($variant, 'updatedAt')),
            ];
        }

        // A product without variants is still sold as one unit; model it as a single variant keyed
        // by the product id so downstream code (catalogs, inventory) has one shape to handle.
        if ($variants === []) {
            $variants[] = [
                'external_id' => (string) data_get($rawProduct, 'id'),
                'name' => null,
                'sku' => data_get($rawProduct, 'sku'),
                'barcode' => data_get($rawProduct, 'barcode'),
                'price' => (float) data_get($rawProduct, 'price1', 0),
                'quantity_in_inventory' => $this->stock(data_get($rawProduct, 'stockAmount')),
                'external_updated_at' => $this->parseDate(data_get($rawProduct, 'updatedAt')),
            ];
        }

        // Echo-loop / change fingerprint per variant, mirroring ShopifyAdapter::fingerprint().
        foreach ($variants as &$variant) {
            $variant['sync_hash'] = $this->fingerprint(array_diff_key($variant, ['external_updated_at' => 1]));
        }
        unset($variant);

        $images = [];

        foreach (data_get($rawProduct, 'images', []) as $image) {
            $url = data_get($image, 'originalUrl');

            if ($url) {
                // Ideasoft returns protocol-relative URLs.
                $images[] = ['url' => str_starts_with($url, '//') ? 'https:'.$url : $url, 'alt' => data_get($image, 'alt') ?: null];
            }
        }

        // With variants, the variants hold the real stock; the parent's stockAmount is their sum,
        // so summing the variants (not adding the parent on top) avoids counting stock twice.
        $quantity = $variants !== []
            ? array_sum(array_column($variants, 'quantity_in_inventory'))
            : $this->stock(data_get($rawProduct, 'stockAmount'));

        return [
            'external_id' => (string) data_get($rawProduct, 'id'),
            'external_updated_at' => $this->parseDate(data_get($rawProduct, 'updatedAt')),
            'name' => data_get($rawProduct, 'fullName') ?? data_get($rawProduct, 'name'),
            'sku' => data_get($rawProduct, 'sku'),
            'barcode' => data_get($rawProduct, 'barcode'),
            'description' => data_get($rawProduct, 'detail.details'),
            'brand' => data_get($rawProduct, 'brand.name'),
            'categories' => array_values(array_filter(array_map(
                fn ($c) => data_get($c, 'tree') ?? data_get($c, 'name'),
                data_get($rawProduct, 'categories', [])
            ))),
            'price' => (float) data_get($rawProduct, 'price1', 0),
            'currency' => data_get($rawProduct, 'currency.abbr'),
            'quantity_in_inventory' => $quantity,
            'is_active' => (int) data_get($rawProduct, 'status', 1) === 1,
            'images' => $images,
            'variants' => $variants,
            'sync_hash' => $this->fingerprint([
                'name' => data_get($rawProduct, 'fullName') ?? data_get($rawProduct, 'name'),
                'description' => data_get($rawProduct, 'detail.details'),
                'status' => data_get($rawProduct, 'status'),
                'brand' => data_get($rawProduct, 'brand.name'),
                'categories' => data_get($rawProduct, 'categories'),
                'images' => $images,
                'variants' => array_column($variants, 'sync_hash'),
            ]),
            'raw' => $rawProduct,
        ];
    }

    /**
     * Stable fingerprint of a payload: key order is ignored so reordering is not read as a change.
     *
     * @param  array<mixed>  $payload
     */
    private function fingerprint(array $payload): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = $sort($v);
                }
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * Ideasoft reports stock as a float (e.g. 15.0). Whole units only here; a fractional stock
     * (weight-based items) is rounded down.
     */
    private function stock(mixed $value): int
    {
        return (int) floor((float) $value);
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
        // Ideasoft keeps stock on the product record, so inventory is derived from a full product
        // walk. $since is deliberately not used to filter: like the Shopify reconciler, returning
        // the full set lets the applier no-op on anything that already matches, and a stock change
        // is not guaranteed to move updatedAt.
        $levels = [];

        foreach ($this->groupVariants($this->client->getProducts()) as $group) {
            // One level per sellable unit: each variant when there are variants (the parent's
            // stockAmount is their sum, so it is skipped), otherwise the product itself.
            $units = $group['_variants'] !== [] ? $group['_variants'] : [$group];

            foreach ($units as $unit) {
                $levels[] = [
                    'external_inventory_item_id' => (string) data_get($unit, 'id'),
                    'external_variant_id' => (string) data_get($unit, 'id'),
                    'external_product_id' => (string) data_get($group, 'id'),
                    'sku' => data_get($unit, 'sku'),
                    'available' => $this->stock(data_get($unit, 'stockAmount')),
                    'on_hand' => $this->stock(data_get($unit, 'stockAmount')),
                    'committed' => null,
                    'external_updated_at' => $this->parseDate(data_get($unit, 'updatedAt')),
                ];
            }
        }

        return $levels;
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
