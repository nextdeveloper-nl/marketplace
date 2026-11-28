<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductRelations;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\ProductOfferings;

class ProductRelationsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'related_marketplace_product_id' => 'required|exists:marketplace_products,uuid|uuid',
        'relation_type' => 'required|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * relation_type is a fixed list (check constraint chk_marketplace_product_relations_type), so a bad value is a
     * 422 and not a SQL error. Below the generated rules() so a regeneration keeps it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'relation_type' => [Rule::in(ProductOfferings::RELATION_TYPES)],
            'related_marketplace_product_id' => ['different:marketplace_product_id'],
        ]);
    }
}
