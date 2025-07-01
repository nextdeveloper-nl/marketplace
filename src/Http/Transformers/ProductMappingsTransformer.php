<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductMappingsTransformer;

/**
 * Class ProductMappingsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductMappingsTransformer extends AbstractProductMappingsTransformer
{

    /**
     * @param ProductMappings $model
     *
     * @return array
     */
    public function transform(ProductMappings $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductMappings', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProductMappings', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
