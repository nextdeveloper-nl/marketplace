<?php

namespace NextDeveloper\Marketplace\Helpers;


use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use Illuminate\Support\Facades\Log;

class MappingExternalProduct
{

    /**
     * Maps an external marketplace product to an internal product or product catalog.
     *
     * This method is used to associate a product received from a marketplace provider
     * (such as Trendyol, Getir, etc.) with your internal system records. It first attempts
     * to find a mapping using the external product ID and provider ID. If a mapping exists,
     * it returns either the internal product catalog entry or the main product, depending
     * on the flag provided.
     *
     * Logging:
     * - Logs a warning if the product ID is missing from the input.
     * - Logs a warning if no product mapping is found in the ProductCatalogMappings table.
     * - Logs a warning if the related ProductCatalog is not found.
     * - Logs a warning if the main product is not found when requested.
     *
     * @param array $product The raw product array received from the marketplace API.
     *                       Must include an 'id' key representing the external product ID.
     * @param int $providerId The ID of the marketplace provider (e.g., Trendyol, Yemeksepeti).
     * @param bool $returnMainProduct If true, returns the main internal product
     *                                (`Products` record) instead of the product catalog.
     *
     * @return ProductCatalogs|Products|null Returns the
     *         matched ProductCatalog record by default. If `$returnMainProduct` is true,
     *         returns the main internal Product record. Returns null if mapping fails
     *         at any step.
     */
    public static function mapProduct(array $product, int $providerId, bool $returnMainProduct = false)
    {
        $productId = $product['id'] ?? null;

        if (!$productId) {
            Log::warning(__METHOD__ . ' - Missing product ID in input', [
                'product' => $product,
                'provider_id' => $providerId,
            ]);
        }

        $mappedProduct = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providerId)
            ->where('external_catalog_id', $productId)
            ->first();

        if (!$mappedProduct) {
            Log::warning(__METHOD__ . ' - No product mapping found', [
                'external_product_id' => $productId,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        $productCatalog = ProductCatalogs::find($mappedProduct->marketplace_product_catalog_id);

        if (!$productCatalog) {
            Log::warning(__METHOD__ . ' - No product catalog found for mapping', [
                'product_catalog_id' => $mappedProduct->marketplace_product_catalog_id,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        if (!$returnMainProduct) {
            return $productCatalog;
        }

        $mainProduct = Products::find($productCatalog->marketplace_product_id);

        if (!$mainProduct) {
            Log::warning(__METHOD__ . ' - No main product found for catalog', [
                'product_catalog_id' => $productCatalog->id,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        return $mainProduct;
    }
}
