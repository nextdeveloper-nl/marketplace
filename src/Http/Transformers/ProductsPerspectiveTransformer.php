<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductsPerspective;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductsPerspectiveTransformer;
use NextDeveloper\Marketplace\Services\ProductsPerspectiveService;
use NextDeveloper\Partnership\Database\Models\Accounts;

/**
 * Class ProductsPerspectiveTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductsPerspectiveTransformer extends AbstractProductsPerspectiveTransformer
{
    public function __construct()
    {
        parent::addInclude('catalogs');
    }

    /**
     * @param ProductsPerspective $model
     *
     * @return array
     */
    public function transform(ProductsPerspective $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductsPerspective', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('ProductsPerspective', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }

    public function includeCatalogs(ProductsPerspective $model)
    {
        $product = ProductsPerspectiveService::getBySlug($model->slug);

        $catalogs = ProductCatalogs::where('marketplace_product_id', $product->id)->get();

        return $this->collection($catalogs, app(ProductCatalogsTransformer::class));
    }
}
