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
        'action' => 'nullable|string',
        'url' => 'nullable|string',
        'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        'api_config' => 'nullable',
        'is_active' => 'boolean',
        'adapter' => 'nullable|string',
        // access_token_enc/webhook_secret_enc/client_secret_enc are encrypted-column
        // mutators on Providers, not plain schema columns, so the generator never
        // picks them up here. Without these three, validated() silently drops the
        // credential fields and a connect/edit save looks like it worked while
        // writing no credential at all.
        'access_token_enc' => 'nullable|string',
        'webhook_secret_enc' => 'nullable|string',
        'client_secret_enc' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}