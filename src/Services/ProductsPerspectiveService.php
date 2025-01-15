<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Database\Models\Domains;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Filters\ProductsQueryFilter;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogsPerspective;
use NextDeveloper\Marketplace\Database\Models\ProductsPerspective;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductsPerspectiveService;

/**
 * This class is responsible from managing the data for ProductsPerspective
 *
 * Class ProductsPerspectiveService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProductsPerspectiveService extends AbstractProductsPerspectiveService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
    public static function getPublicProducts($marketUuid) : ?Collection
    {
        try {
            $marketplace = Markets::where('uuid', $marketUuid)->first();
        } catch (\Exception $e) {
            return new Collection();
        }

        $products = ProductsPerspective::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('is_in_maintenance', false)
            ->where('is_invisible', false)
            ->where('marketplace_market_id', $marketplace->id)
            ->get();

        return $products;
    }

    public static function getBySlug($slug) : ?ProductsPerspective
    {
        $product = ProductsPerspective::where('slug', $slug)->first();

        return $product;
    }

    public static function getCatalogsByProductUuid($uuid)
    {
        $product = ProductsPerspective::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('uuid', $uuid)
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('is_in_maintenance', false)
            ->where('is_invisible', false)
            ->first();

        if($product)
            return ProductCatalogsPerspective::withoutGlobalScope(AuthorizationScope::class)
                ->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_product_id', $product->id)
                ->where('is_public', true)
                ->get();

        return null;
    }
}
