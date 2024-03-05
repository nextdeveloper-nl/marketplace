<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class MarketsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractMarketsTransformer extends AbstractTransformer
{

    /**
     * @param Markets $model
     *
     * @return array
     */
    public function transform(Markets $model)
    {
                        $commonDomainId = \NextDeveloper\Commons\Database\Models\Domains::where('id', $model->common_domain_id)->first();
                    $commonCurrencyId = \NextDeveloper\Commons\Database\Models\Currencies::where('id', $model->common_currency_id)->first();
                    $commonLanguageId = \NextDeveloper\Commons\Database\Models\Languages::where('id', $model->common_language_id)->first();
                    $commonCountryId = \NextDeveloper\Commons\Database\Models\Countries::where('id', $model->common_country_id)->first();
        
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'name'  =>  $model->name,
            'description'  =>  $model->description,
            'common_domain_id'  =>  $commonDomainId ? $commonDomainId->uuid : null,
            'is_public'  =>  $model->is_public,
            'is_active'  =>  $model->is_active,
            'common_currency_id'  =>  $commonCurrencyId ? $commonCurrencyId->uuid : null,
            'common_language_id'  =>  $commonLanguageId ? $commonLanguageId->uuid : null,
            'common_country_id'  =>  $commonCountryId ? $commonCountryId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE


}
