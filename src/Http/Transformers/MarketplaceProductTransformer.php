<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProduct;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractMarketplaceProductTransformer;

/**
 * Class MarketplaceProductTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class MarketplaceProductTransformer extends AbstractMarketplaceProductTransformer {

    /**
     * @param MarketplaceProduct $model
     *
     * @return array
     */
    public function transform(MarketplaceProduct $model) {
        $transformed = Cache::get(
            CacheHelper::getKey('MarketplaceProduct', $model->uuid, 'Transformed')
        );

        if($transformed)
            return $transformed;

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('MarketplaceProduct', $model->uuid, 'Transformed'),
            $transformed
        );

        return parent::transform($model);
    }
}
