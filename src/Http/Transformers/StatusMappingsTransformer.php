<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\StatusMappings;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractStatusMappingsTransformer;

/**
 * Class StatusMappingsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class StatusMappingsTransformer extends AbstractStatusMappingsTransformer
{

    /**
     * @param StatusMappings $model
     *
     * @return array
     */
    public function transform(StatusMappings $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('StatusMappings', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('StatusMappings', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
