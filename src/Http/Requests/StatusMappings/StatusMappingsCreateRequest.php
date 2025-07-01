<?php

namespace NextDeveloper\Marketplace\Http\Requests\StatusMappings;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class StatusMappingsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_provider_id' => 'nullable|exists:marketplace_providers,uuid|uuid',
        'external_status' => 'required|string',
        'normalized_status' => 'required|string',
        'description' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}