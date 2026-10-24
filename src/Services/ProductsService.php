<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Marketplace\Database\Filters\ProductsQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductsService;

/**
 * This class is responsible from managing the data for Products
 *
 * Class ProductsService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProductsService extends AbstractProductsService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
    public static function get(?ProductsQueryFilter $filter = null, array $params = []): \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return parent::get($filter, $params);
    }

    /**
     * Added: two-way sync of one eBay-linked product (the product page "Sync with eBay" action).
     * Looks the product up through the scoped model, so users can only sync products they can see,
     * and requires update rights on it because the sync can change it.
     *
     * @param  string  $ref  Product uuid (public id)
     * @param  string|null  $providerRef  eBay provider uuid, needed only when the product has several
     * @return array<string, mixed>
     */
    public static function syncWithEbay(string $ref, ?string $providerRef = null, bool $dryRun = false, ?string $prefer = null): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\EbayProductSyncService::forProduct($product, $providerRef)
            ->sync($dryRun, $prefer);
    }
    /**
     * Added: link an existing local product to eBay (by SKU, or by creating the listing) so that
     * "Sync with eBay" works for it. Same lookup and permission rules as syncWithEbay().
     *
     * @param  array<string, mixed>  $options  provider_id, sku, create_listing, dry_run, category_id, aspects, image_urls, condition
     * @return array<string, mixed>
     */
    public static function linkToEbay(string $ref, array $options = []): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\EbayLinkService::forProduct($product, $options['provider_id'] ?? null)
            ->link($options);
    }

    /**
     * Added: link an existing local product (and its catalog entries) to the product with the same
     * SKUs on a connected Shopify shop. Same lookup and permission rules as linkToEbay().
     *
     * @param  array<string, mixed>  $options  provider_id, sku, dry_run
     * @return array<string, mixed>
     */
    public static function linkToShopify(string $ref, array $options = []): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\ShopifyLinkService::forProduct($product, $options['provider_id'] ?? null)
            ->link($options);
    }
}
