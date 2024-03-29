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
        'highlights' => 'nullable|string',
        'after_sales_introduction' => 'nullable|string',
        'support_content' => 'nullable|string',
        'refund_policy' => 'nullable|string',
        'eula' => 'nullable|string',
        'subscription_type' => '',
        'slug' => 'nullable|string',
        'version' => 'nullable|string',
        'product_type' => '',
        'is_in_maintenance' => 'boolean',
        'is_public' => 'boolean',
        'is_invisible' => 'boolean',
        'is_active' => 'boolean',
        'common_category_id' => 'nullable|exists:common_categories,uuid|uuid',
        'common_country_id' => 'nullable|exists:common_countries,uuid|uuid',
        'common_language_id' => 'required|exists:common_languages,uuid|uuid',
        'tags' => '',
        'is_service' => 'boolean',
        'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n

}