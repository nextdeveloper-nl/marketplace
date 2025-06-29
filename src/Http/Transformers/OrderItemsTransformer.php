<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\OrderItems;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractOrderItemsTransformer;

/**
 * Class OrderItemsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class OrderItemsTransformer extends AbstractOrderItemsTransformer
{

    /**
     * @param OrderItems $model
     *
     * @return array
     */
    public function transform(OrderItems $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('OrderItems', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('OrderItems', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
