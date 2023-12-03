<?php

namespace NextDeveloper\Marketplace\Http\Requests\Products;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name'                     => 'nullable|string|max:500',
        'description'              => 'nullable|string',
        'content'                  => 'nullable|string',
        'highlights'               => 'nullable|string',
        'after_sales_introduction' => 'nullable|string',
        'support_content'          => 'nullable|string',
        'refund_policy'            => 'nullable|string',
        'eula'                     => 'nullable|string',
        'slug'                     => 'nullable|string|max:500',
        'version'                  => 'nullable|string|max:20',
        'product_type'             => '',
        'management_class'         => 'nullable|string|max:500',
        'discount_rate'            => 'boolean',
        'is_maintenance'           => 'boolean',
        'is_public'                => 'boolean',
        'is_invisible'             => 'boolean',
        'is_active'                => 'boolean',
        'common_category_id'       => 'nullable|exists:common_categories,uuid|uuid',
        'common_country_id'        => 'nullable|exists:common_countries,uuid|uuid',
        'common_language_id'       => 'nullable|exists:common_languages,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n
}