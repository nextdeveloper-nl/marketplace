<?php

namespace NextDeveloper\Marketplace\Helpers;


use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;

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
     * @param int $productCatalogId
     * @param int $providerId The ID of the marketplace provider (e.g., Trendyol, Yemeksepeti).
     * @return ProductCatalogs|null Returns the
     *         matched ProductCatalog record by default. If `$returnMainProduct` is true,
     *         returns the main internal Product record. Returns null if mapping fails
     *         at any step.
     */
    public static function mapProductCatalog(int $productCatalogId, int $providerId): ?ProductCatalogs
    {

        $mappedProduct = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providerId)
            ->where('external_catalog_id', $productCatalogId)
            ->first();

        if (!$mappedProduct) {
            Log::warning(__METHOD__ . ' - No product mapping found', [
                'external_product_id' => $productCatalogId,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        $productCatalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->where('id',$mappedProduct->marketplace_product_catalog_id)
            ->first();

        if (!$productCatalog) {
            Log::warning(__METHOD__ . ' - No product catalog found for mapping', [
                'product_catalog_id' => $mappedProduct->marketplace_product_catalog_id,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        return $productCatalog;

    }


    /**
     * Maps an external marketplace product to an internal product.
     *
     * This method is used to associate a product received from a marketplace provider
     * (such as Trendyol, Getir, etc.) with your internal system records. It first attempts
     * to find a mapping using the external product ID and provider ID. If a mapping exists,
     * it returns the main internal product.
     *
     * Logging:
     * - Logs a warning if the product ID is missing from the input.
     * - Logs a warning if no product mapping is found in the ProductCatalogMappings table.
     * - Logs a warning if the related Product is not found.
     *
     * @param int $productId
     * @param int $providerId The ID of the marketplace provider (e.g., Trendyol, Yemeksepeti).
     *
     * @return Products|null Returns the main internal Product record or null if mapping fails.
     */
    public static function mapMainProduct(int $productId, int $providerId): ?Products
    {

        $mappedProduct = ProductMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providerId)
            ->where('external_product_id', $productId)
            ->first();

        if (!$mappedProduct) {
            Log::warning(__METHOD__ . ' - No product mapping found', [
                'external_product_id' => $productId,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        $mainProduct = Products::withoutGlobalScope(AuthorizationScope::class)
            ->where('id', $mappedProduct->marketplace_product_id)
            ->first();

        if (!$mainProduct) {
            Log::warning(__METHOD__ . ' - No main product found for mapping', [
                'product_id' => $mappedProduct->marketplace_product_id,
                'provider_id' => $providerId,
            ]);
            return null;
        }

        return $mainProduct;
    }
}
