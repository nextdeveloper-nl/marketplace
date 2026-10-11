<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;

/**
 * Applies one normalized Ideasoft record to the local model graph.
 *
 * Counterpart of ShopifyApplyService for Ideasoft, pull-only: the scheduled sync jobs call it, so
 * there is exactly one place that decides what a record does to the database. Every write is
 * quiet (saveQuietly / updateQuietly) so an inbound apply can never fire observers that would
 * queue an outbound push.
 *
 * Join keys are Ideasoft ids kept in the mapping tables (never SKU: it is not guaranteed unique).
 */
class IdeasoftApplyService
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const APPLIED = 'applied';

    public const UNCHANGED = 'unchanged';

    public const UNMAPPED = 'unmapped';

    public function __construct(private readonly Providers $provider, private readonly ?int $marketCurrencyId = null) {}

    /**
     * @param  array<string, mixed>  $n  Output of IdeasoftAdapter::normalizeProductData()
     */
    public function applyProduct(array $n, bool $dryRun = false): string
    {
        $mapping = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_product_id', $n['external_id'])
            ->first();

        // Unchanged payload: nothing to write, only note that we looked.
        if ($mapping && $mapping->sync_hash === $n['sync_hash']) {
            if (! $dryRun) {
                $mapping->last_synced_at = Carbon::now();
                $mapping->saveQuietly();
            }

            return self::SKIPPED;
        }

        // Ordering guard: never overwrite with an older snapshot.
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
            'marketplace_market_id' => $this->provider->marketplace_market_id,
            'marketplace_provider_id' => $this->provider->id,
            'iam_account_id' => $this->provider->iam_account_id,
            'iam_user_id' => $this->provider->iam_user_id,
            'metadata' => [
                'ideasoft' => [
                    'external_id' => $n['external_id'],
                    'brand' => $n['brand'],
                    'categories' => $n['categories'],
                    'currency' => $n['currency'],
                    'images' => $n['images'],
                ],
            ],
        ];

        $outcome = self::UPDATED;

        if (! $product) {
            $product = new Products;
            // Marketplace slugs are platform-wide, so the name slug gets a deterministic suffix.
            $attributes['slug'] = Str::slug($n['name'] ?: 'ideasoft-product')
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
                'ideasoft' => [
                    'external_variant_id' => $variant['external_id'],
                    'barcode' => $variant['barcode'],
                ],
            ]),
        ];

        // Stock is seeded on create only; the inventory job owns it afterwards, so the two
        // schedules never flap against each other.
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
        // Ideasoft has no separate inventory-item id: the variant (or product) id is the stock key.
        $mapping->external_inventory_item_id = (string) $variant['external_id'];
        $mapping->saveQuietly();

        return $isNew ? self::CREATED : self::UPDATED;
    }

    /**
     * @param  array<string, mixed>  $level  One entry from IdeasoftAdapter::fetchInventoryLevels()
     */
    public function applyInventoryLevel(array $level, bool $dryRun = false): string
    {
        $mapping = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('external_catalog_id', $level['external_variant_id'])
            ->first();

        if (! $mapping) {
            // The product sync has not created this variant yet; it will run first on a full sync.
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

        if ($dryRun) {
            return self::APPLIED;
        }

        // Pull-only for now: Ideasoft is the stock authority until push is enabled.
        $catalog->updateQuietly(['quantity_in_inventory' => $available]);

        $mapping->external_updated_at = $level['external_updated_at'];
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();

        return self::APPLIED;
    }

    private function isStale(mixed $appliedAt, mixed $incomingAt): bool
    {
        if (! $appliedAt || ! $incomingAt) {
            return false;
        }

        return Carbon::parse($appliedAt)->greaterThan(Carbon::parse($incomingAt));
    }
}
