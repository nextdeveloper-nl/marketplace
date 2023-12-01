<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductCatalogsTransformer;

/**
 * Class ProductCatalogsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductCatalogsTransformer extends AbstractProductCatalogsTransformer
{

    /**
     * @param ProductCatalogs $model
     *
     * @return array
     */
    public function transform(ProductCatalogs $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductCatalogs', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProductCatalogs', $model->uuid, 'Transformed'),
            $transformed
        );

        return parent::transform($model);
    }
}
