<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductCatalogs;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductCatalogsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name'                   => 'nullable|string|max:500',
        'agreement'              => 'nullable|string',
        'args'                   => 'nullable',
        'price'                  => 'nullable|numeric',
        'currency_code'          => 'string|max:3',
        'subscription_type'      => '',
        'marketplace_product_id' => 'nullable|exists:marketplace_products,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}