<?php

namespace NextDeveloper\Marketplace\Http\Requests\StockMovements;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\StockLedger;

class StockMovementsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_product_catalog_id' => 'required|exists:marketplace_product_catalogs,uuid|uuid',
        'movement_type' => 'required|string',
        'quantity' => 'required|integer',
        'moved_at' => 'date',
        'notes' => 'nullable|string',
        'crm_opportunity_id' => 'nullable|exists:crm_opportunities,uuid|uuid',
        'crm_quote_id' => 'nullable|exists:crm_quotes,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * The fixed lists the table's check constraints enforce, kept here so a bad value is a 422 and not a SQL error.
     * Added below the generated rules() so a regeneration keeps them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'movement_type' => [Rule::in(StockLedger::movementTypes())],
            'quantity' => ['not_in:0'],
        ]);
    }
}