<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductCatalogs;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductCatalogsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name'                   => 'required|string|max:500',
        'agreement'              => 'nullable|string',
        'args'                   => 'nullable',
        'price'                  => 'required|numeric',
        'currency_code'          => 'string|max:3',
        'subscription_type'      => '',
        'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}