<?php

namespace NextDeveloper\Marketplace\Http\Requests\Providers;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProvidersUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'description' => 'nullable|string',
        'action' => 'nullable|string',
        'url' => 'nullable|string',
        'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        'api_config' => 'nullable',
        'is_active' => 'boolean',
        'adapter' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}