<?php

namespace NextDeveloper\Marketplace\Http\Requests\Products;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Helpers\ProductPublishing;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Services\ProductsService;

class ProductsUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'nullable|string',
        'description' => 'nullable|string',
        'content' => 'nullable|string',
        'highlights' => 'nullable',
        'after_sales_introduction' => 'nullable|string',
        'support_content' => 'nullable|string',
        'refund_policy' => 'nullable|string',
        'eula' => 'nullable|string',
        'subscription_type' => '',
        'version' => 'nullable|string',
        'product_type' => '',
        'is_in_maintenance' => 'boolean',
        'is_public' => 'boolean',
        'is_invisible' => 'boolean',
        'is_active' => 'boolean',
        'common_category_id' => 'nullable|exists:common_categories,uuid|uuid',
        'tags' => '',
        'is_service' => 'boolean',
        'marketplace_market_id' => 'nullable|exists:marketplace_markets,uuid|uuid',
        'sales_pitch' => 'nullable|string',
        'marketplace_provider_id' => 'nullable|exists:marketplace_providers,uuid|uuid',
        'payment_gateway_mappings' => 'nullable',
        'is_additional_product' => 'boolean',
        'parent_marketplace_product_id' => 'nullable|exists:marketplace_products,uuid|uuid',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n

    /**
     * Added (leo4#1398): a private product needs at least one image before it is made public. A product that is
     * public already is not checked, so editing it keeps working. An unknown product is left to the controller.
     */
    protected function passedValidation(): void
    {
        $ref = $this->route('marketplace_products');
        $product = $ref instanceof Products ? $ref : ProductsService::getByRef((string) $ref);

        if ($product) {
            ProductPublishing::assertCanTurnPublic($product, $this->input('is_public'));
        }
    }
}
