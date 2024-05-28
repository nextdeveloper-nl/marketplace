<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\Subscriptions;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractSubscriptionsTransformer;

/**
 * Class SubscriptionsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class SubscriptionsTransformer extends AbstractSubscriptionsTransformer
{

    /**
     * @param Subscriptions $model
     *
     * @return array
     */
    public function transform(Subscriptions $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Subscriptions', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Subscriptions', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
