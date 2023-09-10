<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Database\Models\ProductCatalog;

/**
 * Class ProductCatalogTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractProductCatalogTransformer extends AbstractTransformer {

    /**
     * @param ProductCatalog $model
     *
     * @return array
     */
    public function transform(ProductCatalog $model) {
                        $marketplaceProductId = \NextDeveloper\Marketplace\Database\Models\MarketplaceProduct::where('id', $model->marketplace_product_id)->first();
            
        return $this->buildPayload([
'id'  =>  $model->uuid,
'name'  =>  $model->name,
'agreement'  =>  $model->agreement,
'args'  =>  $model->args,
'price'  =>  $model->price,
'currency_code'  =>  $model->currency_code,
'subscription_type'  =>  $model->subscription_type,
'marketplace_product_id'  =>  $marketplaceProductId ? $marketplaceProductId->uuid : null,
'created_at'  =>  $model->created_at,
'updated_at'  =>  $model->updated_at,
'deleted_at'  =>  $model->deleted_at,
    ]);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n






}
