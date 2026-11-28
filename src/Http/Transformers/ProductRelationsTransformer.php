<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use NextDeveloper\Marketplace\Database\Models\ProductRelations;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductRelationsTransformer;

/**
 * Class ProductRelationsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductRelationsTransformer extends AbstractProductRelationsTransformer
{

    /**
     * @param ProductRelations $model
     *
     * @return array
     */
    public function transform(ProductRelations $model)
    {
        //  Not cached: the names below belong to the products, and a product rename does not clear this row's keys.
        $transformed = parent::transform($model);

        //  Name and kind of both ends, so a family section can be drawn without one product call per row. Read through
        //  the products' own scopes: a product the user cannot see gives nulls.
        foreach (['marketplace_product_id' => 'product', 'related_marketplace_product_id' => 'related_product'] as $column => $prefix) {
            $product = Products::query()
                ->where('id', $model->{$column})
                ->first(['id', 'name', 'product_type', 'is_service', 'is_active']);

            $transformed[$prefix.'_name'] = $product?->name;
            $transformed[$prefix.'_type'] = $product?->product_type;
            $transformed[$prefix.'_is_active'] = $product?->is_active;
        }

        return $transformed;
    }
}
