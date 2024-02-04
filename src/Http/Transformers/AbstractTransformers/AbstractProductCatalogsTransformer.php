<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class ProductCatalogsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractProductCatalogsTransformer extends AbstractTransformer
{

    /**
     * @param ProductCatalogs $model
     *
     * @return array
     */
    public function transform(ProductCatalogs $model)
    {
                        $commonCurrencyCodeId = \NextDeveloper\Commons\Database\Models\CurrencyCodes::where('id', $model->common_currency_code_id)->first();
                    $marketplaceProductId = \NextDeveloper\Marketplace\Database\Models\Products::where('id', $model->marketplace_product_id)->first();
        
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'name'  =>  $model->name,
            'agreement'  =>  $model->agreement,
            'args'  =>  $model->args,
            'price'  =>  $model->price,
            'common_currency_code_id'  =>  $commonCurrencyCodeId ? $commonCurrencyCodeId->uuid : null,
            'marketplace_product_id'  =>  $marketplaceProductId ? $marketplaceProductId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n









}
