<?php

namespace NextDeveloper\Marketplace\Http\Requests\Markets;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class MarketsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'description' => 'nullable|string',
        'common_domain_id' => 'nullable|exists:common_domains,uuid|uuid',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'common_currency_id' => 'nullable|exists:common_currencies,uuid|uuid',
        'common_language_id' => 'nullable|exists:common_languages,uuid|uuid',
        'common_country_id' => 'nullable|exists:common_countries,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}