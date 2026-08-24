<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Database\Models\Currencies;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\CustomerMappings;
use NextDeveloper\Marketplace\Database\Models\OrderItems;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Helpers\ShopifyCustomerResolver;

/**
 * Applies one normalized Shopify record to the local model graph.
 *
 * This is deliberately separate from the jobs that fetch. The scheduled pull
 * (SyncShopify*Job) and the webhook processor (ProcessShopifyWebhookJob) must
 * apply a record identically — same echo-loop fingerprint, same ordering guard,
 * same quiet writes — and the only way to guarantee that is for both to call
 * the same code. The jobs own iteration, counting and cursors; this owns what
 * a single record does to the database.
 *
 * Every write here is quiet (saveQuietly / updateQuietly). That is layer 2 of
 * the echo-loop guard: an inbound apply must not fire observers that would
 * queue an outbound push, which Shopify would send straight back to us.
 */
class ShopifyApplyService
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const APPLIED = 'applied';

    public const UNCHANGED = 'unchanged';

    public const STALE_REMOTE = 'stale_remote';

    public const LOCAL_NEWER = 'local_newer';

    public const UNMAPPED = 'unmapped';

    public const DELETED = 'deleted';

    public const NOT_FOUND = 'not_found';

    /** @var array<string, int|null> */
    private array $currencyCache = [];

    public function __construct(private readonly Providers $provider, private readonly ?int $marketCurrencyId = null) {}

    // -------------------------------------------------------------------------
    // Products and variants
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $n  Output of ShopifyAdapter::normalizeProductData()
     */
    public function applyProduct(array $n, bool $dryRun = false): string
    {
        $mapping = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_product_id', $n['external_id'])
            ->first();

        // Echo-loop layer 1: an identical payload is our own write coming back.
        if ($mapping && $mapping->sync_hash === $n['sync_hash']) {
            if (! $dryRun) {
                $mapping->last_synced_at = Carbon::now();
                $mapping->saveQuietly();
            }

            return self::SKIPPED;
        }

        // Ordering guard: Shopify does not order webhook deliveries.
        if ($mapping && $this->isStale($mapping->external_updated_at, $n['external_updated_at'])) {
            return self::SKIPPED;
        }

        if ($dryRun) {
            return $mapping ? self::UPDATED : self::CREATED;
        }

        $product = $mapping
            ? Products::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($mapping->marketplace_product_id)
            : null;

        $attributes = [
            'name' => (string) $n['name'],
            'description' => (string) ($n['description'] ?? ''),
            'is_active' => (bool) $n['is_active'],
            'is_public' => false,
            'tags' => $n['tags'] ?: null,
            'marketplace_market_id' => $this->provider->marketplace_market_id,
            'marketplace_provider_id' => $this->provider->id,
            'iam_account_id' => $this->provider->iam_account_id,
            'iam_user_id' => $this->provider->iam_user_id,
            'metadata' => [
                'shopify' => [
                    'external_id' => $n['external_id'],
                    'handle' => $n['slug'],
                    'vendor' => $n['vendor'],
                    'category_name' => $n['category_name'],
                    'images' => $n['images'],
                ],
            ],
        ];

        $outcome = self::UPDATED;

        if (! $product) {
            $product = new Products;
            // The Shopify handle is unique per shop only, while marketplace
            // slugs are platform-wide, so it is suffixed deterministically.
            $attributes['slug'] = Str::slug(($n['slug'] ?: $n['name']) ?: 'shopify-product')
                .'-'.substr(hash('sha256', $this->provider->id.':'.$n['external_id']), 0, 6);
            $outcome = self::CREATED;
        }

        $product->fill($attributes);
        $product->saveQuietly();

        if (! $mapping) {
            $mapping = new ProductMappings;
            $mapping->fill([
                'marketplace_provider_id' => $this->provider->id,
                'external_product_id' => (string) $n['external_id'],
                'marketplace_product_id' => $product->id,
            ]);
        } else {
            $mapping->marketplace_product_id = $product->id;
        }

        $mapping->sync_hash = $n['sync_hash'];
        $mapping->external_updated_at = $n['external_updated_at'];
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        foreach ($n['variants'] as $variant) {
            $this->applyVariant($product, $variant, (string) $n['name']);
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    public function applyVariant(Products $product, array $variant, string $productName): string
    {
        $mapping = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_catalog_id', $variant['external_id'])
            ->first();

        if ($mapping && $mapping->sync_hash === $variant['sync_hash']) {
            $mapping->last_synced_at = Carbon::now();
            $mapping->saveQuietly();

            return self::SKIPPED;
        }

        if ($mapping && $this->isStale($mapping->external_updated_at, $variant['external_updated_at'])) {
            return self::SKIPPED;
        }

        $catalog = $mapping
            ? ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($mapping->marketplace_product_catalog_id)
            : null;

        $name = $variant['name'] && $variant['name'] !== 'Default Title'
            ? $productName.' — '.$variant['name']
            : $productName;

        $isNew = $catalog === null;
        $catalog ??= new ProductCatalogs;

        $attributes = [
            'name' => $name,
            'marketplace_product_id' => $product->id,
            'price' => (float) $variant['price'],
            'sku' => $variant['sku'],
            'common_currency_id' => $this->marketCurrencyId,
            'is_public' => false,
            'iam_account_id' => $product->iam_account_id,
            'iam_user_id' => $product->iam_user_id,
            'args' => [
                'shopify' => [
                    'external_variant_id' => $variant['external_id'],
                    'options' => $variant['options'],
                    'compare_at_price' => $variant['compare_at_price'],
                    'is_inventory_tracked' => $variant['is_inventory_tracked'],
                ],
            ],
        ];

        /*
         * Stock is seeded on create only. variant.inventoryQuantity is the sum
         * across every location, while a connection is pinned to one, so on a
         * multi-location product the two disagree by design. Re-applying it on
         * every product sync would clobber the pinned-location figure the
         * inventory path just wrote, and the two schedules would flap against
         * each other. Inventory owns this column after creation.
         */
        if ($isNew) {
            $attributes['quantity_in_inventory'] = (int) $variant['quantity_in_inventory'];
        }

        $catalog->fill($attributes);
        $catalog->saveQuietly();

        if (! $mapping) {
            $mapping = new ProductCatalogMappings;
            $mapping->fill([
                'marketplace_provider_id' => $this->provider->id,
                'external_catalog_id' => (string) $variant['external_id'],
                'marketplace_product_catalog_id' => $catalog->id,
            ]);
        } else {
            $mapping->marketplace_product_catalog_id = $catalog->id;
        }

        $mapping->sync_hash = $variant['sync_hash'];
        $mapping->external_updated_at = $variant['external_updated_at'];
        $mapping->last_synced_at = Carbon::now();
        $mapping->external_inventory_item_id = $variant['external_inventory_item_id'];
        $mapping->saveQuietly();

        return $isNew ? self::CREATED : self::UPDATED;
    }

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $level  One entry from ShopifyAdapter::fetchInventoryLevels()
     */
    public function applyInventoryLevel(array $level, bool $dryRun = false): string
    {
        $mapping = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where(function ($query) use ($level) {
                $query->where('external_inventory_item_id', $level['external_inventory_item_id']);

                if (! empty($level['external_variant_id'])) {
                    $query->orWhere('external_catalog_id', $level['external_variant_id']);
                }
            })
            ->first();

        if (! $mapping) {
            return self::UNMAPPED;
        }

        $catalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->find($mapping->marketplace_product_catalog_id);

        if (! $catalog) {
            return self::UNMAPPED;
        }

        $available = (int) $level['available'];

        if ((int) $catalog->quantity_in_inventory === $available) {
            return self::UNCHANGED;
        }

        /*
         * Ordering guard: a level older than the one already applied is stale
         * by definition and must never overwrite it.
         */
        if ($this->isStale($mapping->external_updated_at, $level['external_updated_at'])) {
            return self::STALE_REMOTE;
        }

        /*
         * leo is the inventory authority, so a genuine local edit newer than the
         * remote level wins and is pushed back by the push jobs.
         *
         * "Genuine" is load-bearing: our own writes bump catalogs.updated_at
         * too, so comparing it against the remote level directly would mark
         * every row locally-newer the moment we touched it, and inventory pull
         * would silently never apply again. mapping.last_synced_at is written
         * immediately after each of our writes, so an updated_at beyond it is
         * somebody else's edit.
         */
        $localEditedAt = $catalog->updated_at ? Carbon::parse($catalog->updated_at) : null;
        $lastSyncedAt = $mapping->last_synced_at ? Carbon::parse($mapping->last_synced_at) : null;

        $isLocalEdit = $localEditedAt !== null
            && ($lastSyncedAt === null || $localEditedAt->greaterThan($lastSyncedAt));

        if ($isLocalEdit && $level['external_updated_at']
            && $localEditedAt->greaterThan($level['external_updated_at'])) {
            return self::LOCAL_NEWER;
        }

        if ($dryRun) {
            return self::APPLIED;
        }

        $catalog->updateQuietly(['quantity_in_inventory' => $available]);

        $mapping->external_inventory_item_id = $mapping->external_inventory_item_id
            ?: $level['external_inventory_item_id'];
        $mapping->external_updated_at = $level['external_updated_at'];
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        return self::APPLIED;
    }

    // -------------------------------------------------------------------------
    // Customers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $n  Output of ShopifyAdapter::normalizeCustomerData()
     */
    public function applyCustomer(array $n, bool $dryRun = false): ?CustomerMappings
    {
        return ShopifyCustomerResolver::resolve($this->provider, $n, $dryRun);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $n  Output of ShopifyAdapter::normalizeOrderData()
     */
    public function applyOrder(array $n, bool $dryRun = false): string
    {
        $existing = Orders::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_order_id', $n['external_order_id'])
            ->first();

        if ($existing && data_get($existing->marketplace_metadata, 'sync_hash') === $n['sync_hash']) {
            return self::SKIPPED;
        }

        if ($existing && $this->isStale(data_get($existing->marketplace_metadata, 'external_updated_at'), $n['external_updated_at'])) {
            return self::SKIPPED;
        }

        if ($dryRun) {
            return $existing ? self::UPDATED : self::CREATED;
        }

        $customerUserId = null;

        if (! empty($n['customer']['email'])) {
            $customerUserId = $this->applyCustomer($n['customer'])?->iam_user_id;
        }

        $order = $existing ?? new Orders;
        $order->fill([
            'marketplace_market_id' => $this->provider->marketplace_market_id,
            'marketplace_provider_id' => $this->provider->id,
            'external_order_id' => (string) $n['external_order_id'],
            'external_order_number' => (string) $n['external_order_number'],
            'status' => $n['status'],
            'ordered_at' => $n['ordered_at'],
            'cancelled_at' => $n['cancelled_at'],
            'customer_note' => $n['customer_note'],
            'subtotal_amount' => $n['subtotal_amount'],
            'tax_amount' => $n['tax_amount'],
            'discount_amount' => $n['discount_amount'],
            'delivery_fee' => $n['delivery_fee'],
            'total_amount' => $n['total_amount'],
            'customer_data' => $n['customer'],
            'delivery_address' => $n['delivery_address'],
            'marketplace_metadata' => [
                'sync_hash' => $n['sync_hash'],
                'external_updated_at' => $n['external_updated_at']?->toIso8601String(),
                'financial_status' => data_get($n['raw_order_data'], 'displayFinancialStatus'),
                'fulfillment_status' => data_get($n['raw_order_data'], 'displayFulfillmentStatus'),
                'billing_address' => $n['billing_address'],
                'shop_currency_code' => $n['shop_currency_code'],
                'presentment_currency_code' => $n['presentment_currency_code'],
            ],
            'raw_order_data' => $n['raw_order_data'],
            'last_synced_at' => Carbon::now(),
            'tags' => [$this->provider->name],
            'provider' => 'Shopify',
            'iam_account_id' => $this->provider->iam_account_id,
            'iam_user_id' => $this->provider->iam_user_id,
        ]);

        // Added by the phase-1 schema script, so not in the generated $fillable.
        $order->customer_iam_user_id = $customerUserId;
        $order->presentment_total_amount = $n['presentment_total_amount'];
        $order->presentment_currency_id = $this->currencyId($n['presentment_currency_code']);
        $order->saveQuietly();

        $this->applyLineItems($order, $n['line_items']);

        return $existing ? self::UPDATED : self::CREATED;
    }

    /**
     * Line items are rebuilt on every apply: a Shopify order edit can add,
     * remove and requantify lines, and the local rows carry no state of their
     * own, so a deterministic rebuild beats diffing.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function applyLineItems(Orders $order, array $lineItems): void
    {
        OrderItems::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_order_id', $order->id)
            ->forceDelete();

        foreach ($lineItems as $line) {
            $catalogId = null;

            if (! empty($line['external_variant_id'])) {
                $catalogId = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                    ->where('marketplace_provider_id', $this->provider->id)
                    ->where('external_catalog_id', $line['external_variant_id'])
                    ->value('marketplace_product_catalog_id');
            }

            try {
                $item = new OrderItems;
                $item->fill([
                    'marketplace_order_id' => $order->id,
                    'marketplace_product_catalog_id' => $catalogId,
                    'quantity' => (int) $line['quantity'],
                    'price_per_item' => $line['price_per_item'],
                    'total_price' => $line['total_price'],
                    'item_data' => $line['item_data'] + [
                        'external_line_id' => $line['external_line_id'],
                        'external_variant_id' => $line['external_variant_id'],
                        'sku' => $line['sku'],
                    ],
                    'iam_account_id' => $order->iam_account_id,
                    'iam_user_id' => $order->iam_user_id,
                ]);
                $item->saveQuietly();
            } catch (\Throwable $e) {
                // An unmappable line must not cost us the order itself; it
                // survives in raw_order_data and in this log.
                Log::warning(__METHOD__.' - could not persist order item', [
                    'marketplace_order_id' => $order->id,
                    'external_line_id' => $line['external_line_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Deletions
    // -------------------------------------------------------------------------

    /**
     * Soft-delete a product and its variants after a Shopify delete.
     *
     * Delete webhooks carry only an id, and Shopify has no deleted_at, so this
     * is also what the nightly reconciliation sweep calls for ids that have
     * vanished from the remote catalogue.
     */
    public function deleteProduct(string $externalId): string
    {
        $mapping = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_product_id', $externalId)
            ->first();

        if (! $mapping) {
            return self::NOT_FOUND;
        }

        $product = Products::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($mapping->marketplace_product_id);

        if ($product) {
            $catalogIds = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_product_id', $product->id)
                ->pluck('id');

            ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->whereIn('marketplace_product_catalog_id', $catalogIds)
                ->delete();

            ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->whereIn('id', $catalogIds)
                ->delete();

            // Deactivated as well as soft-deleted: anything reading products
            // without honouring the soft delete must still not offer it.
            $product->updateQuietly(['is_active' => false]);
            $product->delete();
        }

        $mapping->delete();

        return self::DELETED;
    }

    public function deleteCustomerMapping(string $externalCustomerId): string
    {
        $mapping = CustomerMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_customer_id', $externalCustomerId)
            ->first();

        if (! $mapping) {
            return self::NOT_FOUND;
        }

        /*
         * Only the mapping goes. The iam_users row is a platform identity that
         * may own orders and, once promoted, CRM data; erasing it because a
         * merchant deleted their copy of the contact would delete somebody
         * else's records. GDPR erasure is the customers/redact topic, which is
         * handled explicitly rather than inferred from a delete.
         */
        $mapping->delete();

        return self::DELETED;
    }

    /**
     * Mark a local order cancelled after an orders/delete or orders/cancelled.
     */
    public function cancelOrder(string $externalOrderId): string
    {
        $order = Orders::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_order_id', $externalOrderId)
            ->first();

        if (! $order) {
            return self::NOT_FOUND;
        }

        $order->updateQuietly([
            'status' => 'cancelled',
            'cancelled_at' => $order->cancelled_at ?: Carbon::now(),
            'last_synced_at' => Carbon::now(),
        ]);

        return self::DELETED;
    }

    // -------------------------------------------------------------------------

    /**
     * Is the incoming record older than what we already applied?
     */
    private function isStale(mixed $appliedAt, mixed $incomingAt): bool
    {
        if (! $appliedAt || ! $incomingAt) {
            return false;
        }

        return Carbon::parse($appliedAt)->greaterThan(Carbon::parse($incomingAt));
    }

    private function currencyId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        if (! array_key_exists($code, $this->currencyCache)) {
            $this->currencyCache[$code] = Currencies::withoutGlobalScopes()
                ->where('code', $code)
                ->orderBy('id')
                ->value('id');
        }

        return $this->currencyCache[$code];
    }
}
