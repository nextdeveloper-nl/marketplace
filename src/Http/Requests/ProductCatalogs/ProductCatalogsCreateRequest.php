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
            'name' => 'required|string',
        'agreement' => 'nullable|string',
        'args' => 'nullable',
        'price' => 'required',
        'common_currency_id' => 'nullable|exists:common_currencies,uuid|uuid',
        'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'tags' => '',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n
}