<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class ProductsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractProductsTransformer extends AbstractTransformer
{

    /**
     * @param Products $model
     *
     * @return array
     */
    public function transform(Products $model)
    {
                        $commonCategoryId = \NextDeveloper\Commons\Database\Models\Categories::where('id', $model->common_category_id)->first();
                    $commonCountryId = \NextDeveloper\Commons\Database\Models\Countries::where('id', $model->common_country_id)->first();
                    $commonLanguageId = \NextDeveloper\Commons\Database\Models\Languages::where('id', $model->common_language_id)->first();
                    $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
                    $iamUserId = \NextDeveloper\IAM\Database\Models\Users::where('id', $model->iam_user_id)->first();
            
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'name'  =>  $model->name,
            'description'  =>  $model->description,
            'content'  =>  $model->content,
            'highlights'  =>  $model->highlights,
            'after_sales_introduction'  =>  $model->after_sales_introduction,
            'support_content'  =>  $model->support_content,
            'refund_policy'  =>  $model->refund_policy,
            'eula'  =>  $model->eula,
            'slug'  =>  $model->slug,
            'version'  =>  $model->version,
            'product_type'  =>  $model->product_type,
            'management_class'  =>  $model->management_class,
            'discount_rate'  =>  $model->discount_rate,
            'is_maintenance'  =>  $model->is_maintenance,
            'is_public'  =>  $model->is_public,
            'is_invisible'  =>  $model->is_invisible,
            'is_active'  =>  $model->is_active,
            'common_category_id'  =>  $commonCategoryId ? $commonCategoryId->uuid : null,
            'common_country_id'  =>  $commonCountryId ? $commonCountryId->uuid : null,
            'common_language_id'  =>  $commonLanguageId ? $commonLanguageId->uuid : null,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'iam_user_id'  =>  $iamUserId ? $iamUserId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
