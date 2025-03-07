<?php

namespace NextDeveloper\Marketplace\Http\Requests\Products;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
        'description' => 'nullable|string',
        'content' => 'nullable|string',
        'highlights' => 'nullable',
        'after_sales_introduction' => 'nullable|string',
        'support_content' => 'nullable|string',
        'refund_policy' => 'nullable|string',
        'eula' => 'nullable|string',
        'subscription_type' => '',
        'version' => 'nullable|string',
        'product_type' => '',
        'is_in_maintenance' => 'boolean',
        'is_public' => 'boolean',
        'is_invisible' => 'boolean',
        'is_active' => 'boolean',
        'common_category_id' => 'nullable|exists:common_categories,uuid|uuid',
        'tags' => '',
        'is_service' => 'boolean',
        'marketplace_market_id' => 'required|exists:marketplace_markets,uuid|uuid',
        'sales_pitch' => 'nullable|string',
        'marketplace_provider_id' => 'nullable|exists:marketplace_providers,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n


}