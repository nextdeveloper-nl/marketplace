<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractMarketsTransformer;

/**
 * Class MarketsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class MarketsTransformer extends AbstractMarketsTransformer
{

    /**
     * @param Markets $model
     *
     * @return array
     */
    public function transform(Markets $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Markets', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Markets', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
