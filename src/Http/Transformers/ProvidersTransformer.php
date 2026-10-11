<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProvidersTransformer;

/**
 * Class ProvidersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProvidersTransformer extends AbstractProvidersTransformer
{

    /**
     * @param Providers $model
     *
     * @return array
     */
    public function transform(Providers $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Providers', $model->uuid, 'Transformed')
        );

        if(!$transformed) {
            $transformed = parent::transform($model);

            Cache::set(
                CacheHelper::getKey('Providers', $model->uuid, 'Transformed'),
                $transformed
            );
        }

        // Added: is_connected tells the panel whether an access token is stored (never the token
        // itself). Computed on every call, outside the cache, so it cannot go stale after the
        // OAuth callback stores a token.
        $transformed['is_connected'] = $model->getDecryptedAccessToken() !== null;

        return $transformed;
    }
}
