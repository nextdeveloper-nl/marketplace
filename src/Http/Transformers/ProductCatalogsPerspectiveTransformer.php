<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogsPerspective;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductCatalogsPerspectiveTransformer;

/**
 * Class ProductCatalogsPerspectiveTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductCatalogsPerspectiveTransformer extends AbstractProductCatalogsPerspectiveTransformer
{

    /**
     * @param ProductCatalogsPerspective $model
     *
     * @return array
     */
    public function transform(ProductCatalogsPerspective $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductCatalogsPerspective', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProductCatalogsPerspective', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
