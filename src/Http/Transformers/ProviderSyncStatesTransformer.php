<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProviderSyncStates;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProviderSyncStatesTransformer;

/**
 * Class ProviderSyncStatesTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProviderSyncStatesTransformer extends AbstractProviderSyncStatesTransformer
{

    /**
     * @param ProviderSyncStates $model
     *
     * @return array
     */
    public function transform(ProviderSyncStates $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProviderSyncStates', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProviderSyncStates', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
