<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * Applies normalized eBay records to the local model graph (pull only).
 *
 * Counterpart of IdeasoftApplyService. The scheduled/on-demand sync jobs call it, so there is
 * exactly one place that decides what an eBay record does to the database. Every write is quiet
 * (saveQuietly / updateQuietly) so an inbound apply can never fire observers that would queue an
 * outbound push.
 *
 * Join keys are eBay ids kept in the mapping tables, never the SKU column on ProductCatalogs
 * (not guaranteed unique):
 *   ProductMappings.external_product_id        = variation group key, or the SKU of a single item
 *   ProductCatalogMappings.external_catalog_id = SKU
 * with the offer id, listing id and offer status next to the SKU on the catalog mapping.
 *
 * eBay has no modification timestamps, so unlike the Ideasoft apply there is no "older snapshot"
 * ordering guard: change detection rests on the content hash alone.
 */
class EbayApplyService
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const APPLIED = 'applied';

    public const UNCHANGED = 'unchanged';

    public const UNMAPPED = 'unmapped';

    public function __construct(private readonly Providers $provider, private readonly ?int $marketCurrencyId = null) {}

    /**
     * Apply the product level (group, or single SKU) of a scan record.
     *
     * @param  array<string, mixed>  $n  Output of EbayAdapter::normalizeProductData()
     */
    public function applyProduct(array $n, bool $dryRun = false): string
    {
        $mapping = $this->productMapping($n['external_id']);

        // Unchanged payload: nothing to write, only note that we looked.
        if ($mapping && $mapping->sync_hash === $n['sync_hash']) {
            if (! $dryRun) {
                $mapping->last_synced_at = Carbon::now();
                $mapping->saveQuietly();
            }

            return self::SKIPPED;
        }

        if ($dryRun) {
            return $mapping ? self::UPDATED : self::CREATED;
        }

        $product = $mapping ? $this->ownedProduct($mapping->marketplace_product_id) : null;

        // A mapping that points at a product this account does not own is invalid: touch nothing.
        if ($mapping && ! $product) {
            return self::SKIPPED;
        }

        $attributes = [
            'name' => (string) $n['name'],
            'description' => (string) ($n['description'] ?? ''),
            'is_active' => (bool) $n['is_active'],
            // Imported products stay private until a merchant publishes them.
            'is_public' => false,
            'marketplace_market_id' => $this->provider->marketplace_market_id,
            'marketplace_provider_id' => $this->provider->id,
            'metadata' => array_merge(is_array($product?->metadata) ? $product->metadata : [], [
                'ebay' => [
                    'external_id' => $n['external_id'],
                    'kind' => $n['kind'],
                    'categories' => $n['categories'],
                    'currency' => $n['currency'],
                    'condition' => $n['condition'],
                    'images' => $n['images'],
                ],
            ]),
        ];

        $outcome = self::UPDATED;

        if (! $product) {
            $product = new Products;
            // Marketplace slugs are platform-wide, so the name slug gets a deterministic suffix.
            $attributes['slug'] = Str::slug($n['name'] ?: 'ebay-product')
                .'-'.substr(hash('sha256', $this->provider->id.':'.$n['external_id']), 0, 6);
            // Ownership is set on create only and never rewritten on an existing product.
            $attributes['iam_account_id'] = $this->provider->iam_account_id;
            $attributes['iam_user_id'] = $this->provider->iam_user_id;
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
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        return $outcome;
    }

    /**
     * Apply one SKU as a catalog entry of an already applied product.
     *
     * @param  string  $productExternalId  external_id of the product this SKU belongs to
     * @param  array<string, mixed>  $variant  Output of EbayAdapter::normalizeVariantData()
     */
    public function applyVariant(string $productExternalId, array $variant, string $productName, bool $dryRun = false): string
    {
        $mapping = $this->catalogMapping($variant['external_id']);

        if ($mapping && $mapping->sync_hash === $variant['sync_hash']) {
            if (! $dryRun) {
                $mapping->last_synced_at = Carbon::now();
                $mapping->saveQuietly();
            }

            return self::SKIPPED;
        }

        if ($dryRun) {
            return $mapping ? self::UPDATED : self::CREATED;
        }

        $productMapping = $this->productMapping($productExternalId);
        $product = $productMapping ? $this->ownedProduct($productMapping->marketplace_product_id) : null;

        if (! $product) {
            // The product level was not applied (or was removed); nothing to attach the SKU to.
            return self::UNMAPPED;
        }

        $catalog = $mapping ? $this->ownedCatalog($mapping->marketplace_product_catalog_id) : null;

        // A mapping that points at a catalog entry this account does not own is invalid.
        if ($mapping && ! $catalog) {
            return self::UNMAPPED;
        }

        $name = $variant['name'] ? $productName.' — '.$variant['name'] : $productName;

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
            'args' => array_merge(is_array($catalog->args) ? $catalog->args : [], [
                'ebay' => [
                    'sku' => $variant['sku'],
                    'offer_id' => $variant['offer_id'],
                    'listing_id' => $variant['listing_id'],
                    'barcode' => $variant['barcode'],
                    'currency' => $variant['currency'],
                ],
            ]),
        ];

        // Stock is seeded on create only; the inventory job owns it afterwards, so the two
        // syncs never flap against each other.
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

        $mapping->external_offer_id = $variant['offer_id'];
        $mapping->external_listing_id = $variant['listing_id'];
        $mapping->offer_status = $variant['offer_status'];
        $mapping->sync_hash = $variant['sync_hash'];
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        // Product-level listing id: a single-item product mirrors its only listing; a group's
        // listing id is set by the group publish, which a later phase handles.
        if ($variant['listing_id'] && $productMapping->external_listing_id !== $variant['listing_id']
            && $productMapping->external_product_id === $variant['external_id']) {
            $productMapping->external_listing_id = $variant['listing_id'];
            $productMapping->saveQuietly();
        }

        return $isNew ? self::CREATED : self::UPDATED;
    }

    /**
     * Apply one SKU's stock level.
     *
     * @param  array<string, mixed>  $level  One entry from EbayAdapter::inventoryLevel()
     */
    public function applyInventoryLevel(array $level, bool $dryRun = false): string
    {
        $mapping = $this->catalogMapping($level['external_variant_id']);

        if (! $mapping) {
            // The product sync has not created this SKU yet; it runs first on a full sync.
            return self::UNMAPPED;
        }

        $catalog = $this->ownedCatalog($mapping->marketplace_product_catalog_id);

        if (! $catalog) {
            return self::UNMAPPED;
        }

        $available = (int) $level['available'];

        if ((int) $catalog->quantity_in_inventory === $available) {
            return self::UNCHANGED;
        }

        if ($dryRun) {
            return self::APPLIED;
        }

        // Pull-only for now: eBay is the stock authority until push is enabled.
        $catalog->updateQuietly(['quantity_in_inventory' => $available]);

        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        return self::APPLIED;
    }

    /**
     * The product behind a mapping, only if it belongs to this provider's account. The lookups
     * drop AuthorizationScope (the sync has no request user), so ownership is checked here instead.
     */
    private function ownedProduct(int $id): ?Products
    {
        $product = Products::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($id);

        if ($product && (int) $product->iam_account_id !== (int) $this->provider->iam_account_id) {
            Log::warning(__METHOD__.' - mapping points at a product of another account, ignored', [
                'provider_id' => $this->provider->id,
                'product_id' => $id,
            ]);

            return null;
        }

        return $product;
    }

    /**
     * The catalog entry behind a mapping, only if it belongs to this provider's account.
     */
    private function ownedCatalog(int $id): ?ProductCatalogs
    {
        $catalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($id);

        if ($catalog && (int) $catalog->iam_account_id !== (int) $this->provider->iam_account_id) {
            Log::warning(__METHOD__.' - mapping points at a catalog entry of another account, ignored', [
                'provider_id' => $this->provider->id,
                'catalog_id' => $id,
            ]);

            return null;
        }

        return $catalog;
    }

    private function productMapping(string $externalId): ?ProductMappings
    {
        return ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_product_id', $externalId)
            ->first();
    }

    private function catalogMapping(string $externalId): ?ProductCatalogMappings
    {
        return ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_catalog_id', $externalId)
            ->first();
    }
}
