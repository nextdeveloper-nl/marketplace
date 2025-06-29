<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\OrderStatusHistories;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractOrderStatusHistoriesTransformer;

/**
 * Class OrderStatusHistoriesTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class OrderStatusHistoriesTransformer extends AbstractOrderStatusHistoriesTransformer
{

    /**
     * @param OrderStatusHistories $model
     *
     * @return array
     */
    public function transform(OrderStatusHistories $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('OrderStatusHistories', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('OrderStatusHistories', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
