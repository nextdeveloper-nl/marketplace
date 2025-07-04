<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractOrdersTransformer;

/**
 * Class OrdersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class OrdersTransformer extends AbstractOrdersTransformer
{

    /**
     * @param Orders $model
     *
     * @return array
     */
    public function transform(Orders $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Orders', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Orders', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
