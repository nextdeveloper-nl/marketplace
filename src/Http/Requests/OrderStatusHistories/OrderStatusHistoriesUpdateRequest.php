<?php

namespace NextDeveloper\Marketplace\Http\Requests\OrderStatusHistories;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class OrderStatusHistoriesUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_order_id' => 'nullable|exists:marketplace_orders,uuid|uuid',
        'old_status' => 'nullable|string',
        'new_status' => 'nullable|string',
        'changed_at' => 'date',
        'notes' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}