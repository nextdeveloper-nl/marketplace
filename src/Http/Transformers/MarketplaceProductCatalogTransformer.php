<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractMarketplaceProductCatalogTransformer;

/**
 * Class MarketplaceProductCatalogTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class MarketplaceProductCatalogTransformer extends AbstractMarketplaceProductCatalogTransformer {

    /**
     * @param MarketplaceProductCatalog $model
     *
     * @return array
     */
    public function transform(MarketplaceProductCatalog $model) {
        $transformed = Cache::get(
            CacheHelper::getKey('MarketplaceProductCatalog', $model->uuid, 'Transformed')
        );

        if($transformed)
            return $transformed;

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('MarketplaceProductCatalog', $model->uuid, 'Transformed'),
            $transformed
        );

        return parent::transform($model);
    }
}
