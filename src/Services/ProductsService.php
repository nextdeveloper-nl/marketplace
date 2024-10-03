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
    public static function get(ProductsQueryFilter $filter = null, array $params = []) {
        $result = self::get($filter, $params);

        dd($result);
    }
}
