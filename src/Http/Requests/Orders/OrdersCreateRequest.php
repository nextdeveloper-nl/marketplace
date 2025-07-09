<?php

namespace NextDeveloper\Marketplace\Http\Requests\Orders;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class OrdersCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        'marketplace_provider_id' => 'nullable|exists:marketplace_providers,uuid|uuid',
        'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'external_order_id' => 'nullable|string|exists:external_orders,uuid|uuid',
        'external_order_number' => 'nullable|string',
        'status' => 'nullable|string',
        'ordered_at' => 'date',
        'accepted_at' => 'nullable|date',
        'prepared_at' => 'nullable|date',
        'dispatched_at' => 'nullable|date',
        'delivered_at' => 'nullable|date',
        'cancelled_at' => 'nullable|date',
        'customer_data' => 'nullable',
        'delivery_address' => 'nullable',
        'marketplace_metadata' => 'nullable',
        'subtotal_amount' => '',
        'delivery_fee' => '',
        'service_fee' => '',
        'tax_amount' => '',
        'discount_amount' => '',
        'total_amount' => '',
        'order_type' => 'string',
        'delivery_method' => 'nullable|string',
        'estimated_delivery_time' => 'nullable|date',
        'raw_order_data' => 'nullable',
        'last_synced_at' => 'date',
        'sync_error_message' => 'nullable|string',
        'customer_note' => 'nullable|string',
        'external_line_id' => 'nullable|string|exists:external_lines,uuid|uuid',
        'tags' => '',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}