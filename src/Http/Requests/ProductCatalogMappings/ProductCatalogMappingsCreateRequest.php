<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductCatalogMappings;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ProductCatalogMappingsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'marketplace_product_catalog_id' => 'required|exists:marketplace_product_catalogs,uuid|uuid',
        'marketplace_provider_id' => 'required|exists:marketplace_providers,uuid|uuid',
        'external_catalog_id' => 'required|string|exists:external_catalogs,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}