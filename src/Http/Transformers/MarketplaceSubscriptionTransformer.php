<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractMarketplaceSubscriptionTransformer;

/**
 * Class MarketplaceSubscriptionTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class MarketplaceSubscriptionTransformer extends AbstractMarketplaceSubscriptionTransformer {

    /**
     * @param MarketplaceSubscription $model
     *
     * @return array
     */
    public function transform(MarketplaceSubscription $model) {
        $transformed = Cache::get(
            CacheHelper::getKey('MarketplaceSubscription', $model->uuid, 'Transformed')
        );

        if($transformed)
            return $transformed;

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('MarketplaceSubscription', $model->uuid, 'Transformed'),
            $transformed
        );

        return parent::transform($model);
    }
}
