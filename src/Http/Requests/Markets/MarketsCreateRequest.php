<?php

namespace NextDeveloper\Marketplace\Http\Requests\Markets;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class MarketsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
        'description' => 'nullable|string',
        'common_domain_id' => 'required|exists:common_domains,uuid|uuid',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'common_currency_id' => 'required|exists:common_currencies,uuid|uuid',
        'common_language_id' => 'required|exists:common_languages,uuid|uuid',
        'common_country_id' => 'required|exists:common_countries,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}