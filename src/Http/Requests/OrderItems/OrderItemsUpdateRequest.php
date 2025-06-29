<?php

namespace NextDeveloper\Marketplace\Http\Requests\OrderItems;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class OrderItemsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_order_id' => 'nullable|exists:marketplace_orders,uuid|uuid',
        'marketplace_product_catalog_id' => 'nullable|exists:marketplace_product_catalogs,uuid|uuid',
        'quantity' => 'integer',
        'price_per_item' => 'nullable',
        'total_price' => 'nullable',
        'modifiers' => 'nullable',
        'special_instructions' => 'nullable|string',
        'item_data' => 'nullable',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}