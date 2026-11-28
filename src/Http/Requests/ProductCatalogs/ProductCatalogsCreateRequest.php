<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductCatalogs;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\StockLedger;

class ProductCatalogsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
        'agreement' => 'nullable|string',
        'args' => 'nullable',
        'price' => 'required',
        'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'tags' => '',
        'sku' => 'nullable|string',
        'quantity_in_inventory' => 'integer',
        'trial_date' => 'integer',
        'features' => 'nullable',
        'is_public' => 'boolean',
        'common_currency_id' => 'nullable|exists:common_currencies,uuid|uuid',
        'stock_status' => 'nullable|string',
        'expected_arrival_at' => 'nullable|date',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n

    /**
     * stock_status is a fixed list (check constraint chk_marketplace_product_catalogs_stock_status), so a bad value
     * is a 422 and not a SQL error. Below the generated rules() so a regeneration keeps it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'stock_status' => [Rule::in(StockLedger::STATUSES)],
        ]);
    }

}