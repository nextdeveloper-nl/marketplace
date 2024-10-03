<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
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
    public static function getBySlug($slug) : ?ProductsPerspective
    {
        $product = ProductsPerspective::where('slug', $slug)->first();

        return $product;
    }
}
