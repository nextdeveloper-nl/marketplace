<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\StockMovements;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractStockMovementsTransformer;

/**
 * Class StockMovementsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class StockMovementsTransformer extends AbstractStockMovementsTransformer
{

    /**
     * @param StockMovements $model
     *
     * @return array
     */
    public function transform(StockMovements $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('StockMovements', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        //  Who recorded the movement, for the history list. The row is already scoped to the account, so its user is
        //  a member of that account; only the display name is exposed.
        $transformed['user_name'] = \NextDeveloper\IAM\Database\Models\Users::withoutGlobalScopes()
            ->where('id', $model->iam_user_id)
            ->value('fullname');

        Cache::set(
            CacheHelper::getKey('StockMovements', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
