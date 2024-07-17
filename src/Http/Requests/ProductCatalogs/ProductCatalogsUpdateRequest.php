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
            'name' => 'nullable|string',
        'agreement' => 'nullable|string',
        'args' => 'nullable',
        'price' => 'nullable',
        'marketplace_product_id' => 'nullable|exists:marketplace_products,uuid|uuid',
        'tags' => '',
        'sku' => 'nullable|string',
        'trial_date' => 'integer',
        'features' => 'nullable',
        'is_public' => 'boolean',
        'quantity_in_inventory' => 'integer',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n
}