<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductsPerspective;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductCatalogsPerspectiveService;

/**
 * This class is responsible from managing the data for ProductCatalogsPerspective
 *
 * Class ProductCatalogsPerspectiveService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProductCatalogsPerspectiveService extends AbstractProductCatalogsPerspectiveService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
    public static function getByProductId($id)
    {
        $product = ProductsPerspective::withoutGlobalScope(AuthorizationScope::class)
            ->where('uuid', $id)
            ->first();

        if(!$product->is_public || !!$product->is_active)
            return null;

        return ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->where('marketplace_product_id', $product->id)
            ->get();
    }
}
