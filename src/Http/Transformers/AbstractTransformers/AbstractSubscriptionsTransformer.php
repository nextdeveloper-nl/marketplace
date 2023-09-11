<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Marketplace\Database\Models\Subscriptions;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class SubscriptionsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractSubscriptionsTransformer extends AbstractTransformer
{

    /**
     * @param Subscriptions $model
     *
     * @return array
     */
    public function transform(Subscriptions $model)
    {
                        $marketplaceProductCatalogId = \NextDeveloper\Marketplace\Database\Models\ProductCatalogs::where('id', $model->marketplace_product_catalog_id)->first();
                    $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
                    $iamUserId = \NextDeveloper\IAM\Database\Models\Users::where('id', $model->iam_user_id)->first();
            
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'marketplace_product_catalog_id'  =>  $marketplaceProductCatalogId ? $marketplaceProductCatalogId->uuid : null,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'iam_user_id'  =>  $iamUserId ? $iamUserId->uuid : null,
            'subscription_data'  =>  $model->subscription_data,
            'subscription_starts_at'  =>  $model->subscription_starts_at,
            'subscription_ends_at'  =>  $model->subscription_ends_at,
            'is_valid'  =>  $model->is_valid == 1 ? true : false,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n





}
