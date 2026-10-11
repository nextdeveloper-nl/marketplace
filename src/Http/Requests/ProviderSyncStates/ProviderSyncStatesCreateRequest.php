<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProviderSyncStates;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProviderSyncStatesCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_provider_id' => 'nullable|exists:marketplace_providers,uuid|uuid',
            'entity_type' => 'required|string',
            'cursor_updated_at' => 'nullable|date',
            'last_run_at' => 'nullable|date',
            'last_success_at' => 'nullable|date',
            'consecutive_failures' => 'nullable|integer',
            'last_error' => 'nullable|string',
            'records_processed' => 'nullable|integer',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
