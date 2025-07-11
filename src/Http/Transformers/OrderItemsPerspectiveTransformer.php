<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\OrderItemsPerspective;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractOrderItemsPerspectiveTransformer;

/**
 * Class OrderItemsPerspectiveTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class OrderItemsPerspectiveTransformer extends AbstractOrderItemsPerspectiveTransformer
{

    /**
     * @param OrderItemsPerspective $model
     *
     * @return array
     */
    public function transform(OrderItemsPerspective $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('OrderItemsPerspective', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('OrderItemsPerspective', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
