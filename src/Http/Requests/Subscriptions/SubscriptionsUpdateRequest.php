<?php

namespace NextDeveloper\Marketplace\Http\Requests\Subscriptions;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class SubscriptionsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_product_catalog_id' => 'nullable|exists:marketplace_product_catalogs,uuid|uuid',
        'subscription_data' => 'nullable',
        'subscription_starts_at' => 'nullable|date',
        'subscription_ends_at' => 'nullable|date',
        'is_valid' => 'boolean',
        'tags' => '',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n
}