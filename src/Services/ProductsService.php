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

        if (! \NextDeveloper\IAM\Helpers\UserHelper::can('update', $product)) {
            throw new \NextDeveloper\Commons\Exceptions\NotAllowedException('You are not allowed to sync this product.');
        }

        return \NextDeveloper\Marketplace\Services\Marketplaces\EbayProductSyncService::forProduct($product, $providerRef)
            ->sync($dryRun, $prefer);
    }
}
