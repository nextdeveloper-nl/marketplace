<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductCatalogMappingsTransformer;

/**
 * Class ProductCatalogMappingsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductCatalogMappingsTransformer extends AbstractProductCatalogMappingsTransformer
{

    /**
     * @param ProductCatalogMappings $model
     *
     * @return array
     */
    public function transform(ProductCatalogMappings $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductCatalogMappings', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProductCatalogMappings', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
