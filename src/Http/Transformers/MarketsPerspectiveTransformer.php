<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\MarketsPerspective;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractMarketsPerspectiveTransformer;

/**
 * Class MarketsPerspectiveTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class MarketsPerspectiveTransformer extends AbstractMarketsPerspectiveTransformer
{

    /**
     * @param MarketsPerspective $model
     *
     * @return array
     */
    public function transform(MarketsPerspective $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('MarketsPerspective', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('MarketsPerspective', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
