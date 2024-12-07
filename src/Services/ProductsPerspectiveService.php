<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Filters\ProductsQueryFilter;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
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
    public static function getPublicProducts() : ?Collection
    {
        $products = ProductsPerspective::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('is_public', true)
            ->where('is_active', true)
            ->where('is_in_maintenance', false)
            ->where('is_invisible', false)
            ->get();

        return $products;
    }

    public static function getBySlug($slug) : ?ProductsPerspective
    {
        $product = ProductsPerspective::where('slug', $slug)->first();

        return $product;
    }
}
