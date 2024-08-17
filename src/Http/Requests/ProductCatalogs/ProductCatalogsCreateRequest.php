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
        'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'tags' => '',
        'sku' => 'nullable|string',
        'quantity_in_inventory' => 'integer',
        'trial_date' => 'integer',
        'features' => 'nullable',
        'is_public' => 'boolean',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n
}