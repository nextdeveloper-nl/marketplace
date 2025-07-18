<?php

namespace NextDeveloper\Marketplace\Helpers;


use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\StatusMappings;
use NextDeveloper\Marketplace\Database\Models\Providers;

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
     * @param Providers $providers The ID of the marketplace provider (e.g., Trendyol, Yemeksepeti).
     * @param string $originalProductName The original name of the product as received from the marketplace.
     * @return ProductCatalogs|null Returns the
     *         matched ProductCatalog record by default. If `$returnMainProduct` is true,
     *         returns the main internal Product record. Returns null if mapping fails
     *         at any step.
     */
    public static function mapProductCatalog(
        int $productCatalogId,
        Providers $providers,
        string $originalProductName
        ): ?ProductCatalogs
    {

        $mappedProduct = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providers->id)
            ->where('external_catalog_id', $productCatalogId)
            ->first();

        if (!$mappedProduct) {
            Log::warning(__METHOD__ . ' - No product catalog mapping found', [
                'external_product_id' => $productCatalogId,
                'provider_id' => $providers->id,
                'original_product_name' => $originalProductName,
                'provider' => $providers->name,
            ]);
            return null;
        }

        $productCatalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->where('id',$mappedProduct->marketplace_product_catalog_id)
            ->first();

        if (!$productCatalog) {
            Log::warning(__METHOD__ . ' - No product catalog found for mapping', [
                'product_catalog_id' => $mappedProduct->marketplace_product_catalog_id,
                'provider_id' => $providers->id,
                'original_product_name' => $originalProductName,
                'provider' => $providers->name,
                'external_product_id' => $productCatalogId,
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
     * @param Providers $providers The ID of the marketplace provider (e.g., Trendyol, Yemeksepeti).
     * @param string $originalProductName The original name of the product as received from the marketplace.
     *
     * @return Products|null Returns the main internal Product record or null if mapping fails.
     */
    public static function mapMainProduct(
        int $productId,
        Providers $providers,
        string $originalProductName
    ): ?Products
    {

        $mappedProduct = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providers->id)
            ->where('external_catalog_id', $productId)
            ->first();

        if (!$mappedProduct) {
            Log::warning(__METHOD__ . ' - No main product mapping found', [
                'external_product_id' => $productId,
                'provider_id' => $providers->id,
                'original_product_name' => $originalProductName,
                'provider' => $providers->name,
            ]);
            return null;
        }

        $mainProductCatalogs = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->where('id', $mappedProduct->marketplace_product_catalog_id)
            ->first();

        if (!$mainProductCatalogs) {
            Log::warning(__METHOD__ . ' - No main product catalog found for mapping', [
                'product_id' => $mappedProduct->marketplace_product_catalog_id,
                'original_product_name' => $originalProductName,
                'external_product_id' => $productId,
                'provider_id' => $providers->id,
                'provider' => $providers->name,
            ]);
            return null;
        }

        $mainProduct = Products::withoutGlobalScope(AuthorizationScope::class)
            ->where('id', $mainProductCatalogs->marketplace_product_id)
            ->first();

        if (!$mainProduct) {
            Log::warning(__METHOD__ . ' - No main product found for mapping', [
                'product_id' => $mainProductCatalogs->marketplace_product_id,
                'original_product_name' => $originalProductName,
                'external_product_id' => $productId,
                'provider_id' => $providers->id,
                'provider' => $providers->name,
            ]);
            return null;
        }

        return $mainProduct;
    }

    /**
     * Maps the status of an external order based on the provider's status mapping.
     *
     * This method retrieves the mapped status for a given external status and provider ID.
     * If no mapping is found, it logs a warning and returns the original status.
     *
     * @param string $status The external status to map.
     * @param int $providerId The ID of the marketplace provider.
     *
     * @return string Returns the mapped status or the original status if no mapping is found.
     */
    public static function mapOrderStatus(string $status, Providers $providers): string
    {
        $statusMapping = StatusMappings::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_provider_id', $providers->id)
            ->where('external_status', $status)
            ->first();

        if (!$statusMapping) {
            Log::warning(__METHOD__ . ' - No status mapping found, original status will be used', [
                'external_status' => $status,
                'provider_id' => $providers->id,
                'provider' => $providers->name,
            ]);
            return $status;
        }

        return $statusMapping->normalized_status;
    }


}
