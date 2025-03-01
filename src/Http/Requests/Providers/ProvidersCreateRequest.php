<?php

namespace NextDeveloper\Marketplace\Http\Requests\Providers;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProvidersCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'description' => 'nullable|string',
        'action' => 'required|string',
        'url' => 'required|string',
        'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}