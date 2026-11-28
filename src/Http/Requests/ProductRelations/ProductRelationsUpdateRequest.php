<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductRelations;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\ProductOfferings;

class ProductRelationsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'relation_type' => 'string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Only the type of a link can change. Moving an item to another family is a delete plus a new link, so the two
     * product ids are not accepted here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->addRules([
            'relation_type' => [Rule::in(ProductOfferings::RELATION_TYPES)],
        ]);
    }
}
