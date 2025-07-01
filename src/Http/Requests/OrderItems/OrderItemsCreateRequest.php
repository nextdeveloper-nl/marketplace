<?php

namespace NextDeveloper\Marketplace\Http\Requests\OrderItems;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class OrderItemsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_order_id' => 'required|exists:marketplace_orders,uuid|uuid',
        'marketplace_product_catalog_id' => 'required|exists:marketplace_product_catalogs,uuid|uuid',
        'quantity' => 'integer',
        'price_per_item' => 'required',
        'total_price' => 'required',
        'modifiers' => 'nullable',
        'special_instructions' => 'nullable|string',
        'item_data' => 'nullable',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}