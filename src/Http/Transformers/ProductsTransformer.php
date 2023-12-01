<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductsTransformer;

/**
 * Class ProductsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductsTransformer extends AbstractProductsTransformer
{

    /**
     * @param Products $model
     *
     * @return array
     */
    public function transform(Products $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Products', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Products', $model->uuid, 'Transformed'),
            $transformed
        );

        return parent::transform($model);
    }
}
