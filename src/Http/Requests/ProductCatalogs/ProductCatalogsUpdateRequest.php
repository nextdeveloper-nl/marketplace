<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductCatalogs;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\ProductOfferings;
use NextDeveloper\Marketplace\Helpers\StockLedger;

class ProductCatalogsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'agreement' => 'nullable|string',
        'args' => 'nullable',
        'price' => 'nullable',
        'marketplace_product_id' => 'nullable|exists:marketplace_products,uuid|uuid',
        'tags' => '',
        'sku' => 'nullable|string',
        'quantity_in_inventory' => 'integer',
        'trial_date' => 'integer',
        'features' => 'nullable',
        'is_public' => 'boolean',
        'common_currency_id' => 'nullable|exists:common_currencies,uuid|uuid',
        'stock_status' => 'nullable|string',
        'expected_arrival_at' => 'nullable|date',
        'offering_type' => 'nullable|string',
        'billing_period' => 'nullable|string',
        'min_rental_duration' => 'nullable|integer',
        'max_rental_duration' => 'nullable|integer',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n

    /**
     * stock_status, offering_type and billing_period are fixed lists (check constraints chk_marketplace_product_catalogs_*),
     * so a bad value is a 422 and not a SQL error. Below the generated rules() so a regeneration keeps it. How the
     * three offering fields fit together is checked on the model (ProductCatalogs::assertConsistentOffering), where
     * an update is merged with the stored row.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'stock_status' => [Rule::in(StockLedger::STATUSES)],
            'offering_type' => [Rule::in(ProductOfferings::OFFERING_TYPES)],
            'billing_period' => [Rule::in(ProductOfferings::BILLING_PERIODS)],
            'min_rental_duration' => ['min:1'],
            'max_rental_duration' => ['min:1'],
        ]);
    }
}