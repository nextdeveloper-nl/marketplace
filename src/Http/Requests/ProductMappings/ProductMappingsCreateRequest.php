<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductMappings;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductMappingsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'marketplace_provider_id' => 'required|exists:marketplace_providers,uuid|uuid',
        'external_product_id' => 'required|string|exists:external_products,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}