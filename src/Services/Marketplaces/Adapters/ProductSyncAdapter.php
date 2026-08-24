<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Illuminate\Support\Carbon;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;

/**
 * Capability interface for marketplaces that can synchronise a product catalogue.
 *
 * Deliberately kept separate from MarketplaceAdapter: the original contract is
 * orders-only and TrendyolGoYemekAdapter implements just that. Adapters declare
 * the capabilities they actually have and callers gate on `instanceof`.
 */
interface ProductSyncAdapter
{
    /**
     * Fetch products changed at or after the given moment.
     *
     * Implementations must apply a small overlap window to the cursor: remote
     * `updated_at` values are server timestamps and records written during a
     * page boundary would otherwise be skipped.
     *
     * @return array<int, array<string, mixed>> Raw product payloads.
     */
    public function fetchProducts(Carbon $since): array;

    /**
     * Push a local product to the marketplace, creating or updating it.
     *
     * @return array<string, mixed> Raw remote payload of the written record.
     */
    public function pushProduct(Products $product): array;

    /**
     * Push a local catalog entry (variant) to the marketplace.
     *
     * @return array<string, mixed> Raw remote payload of the written record.
     */
    public function pushCatalog(ProductCatalogs $catalog): array;

    /**
     * Normalise a raw remote product into our internal shape.
     *
     * The returned array must contain an `external_id` and an
     * `external_updated_at`, since both drive the echo-loop and ordering guards.
     *
     * @param  array<string, mixed>  $rawProduct
     * @return array<string, mixed>
     */
    public function normalizeProductData(array $rawProduct): array;
}
