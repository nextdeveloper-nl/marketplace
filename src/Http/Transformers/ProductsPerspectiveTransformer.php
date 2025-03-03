<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NextDeveloper\Blogs\Database\Models\Posts;
use NextDeveloper\Blogs\Database\Models\PostsPerspective;
use NextDeveloper\Blogs\Http\Transformers\PostsPerspectiveTransformer;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Http\Transformers\MediaTransformer;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
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
        parent::addInclude('blogs');
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
        $transformed['sales_pitch_short'] = Str::words($transformed['sales_pitch'], 20);

        Cache::set(
            CacheHelper::getKey('ProductsPerspective', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }

    public function includeBlogs(ProductsPerspective $model)
    {
        $blogs = PostsPerspective::whereRaw('tags @> ARRAY[\'' . $model->slug . '\']')->get();

        return $this->collection($blogs, app(PostsPerspectiveTransformer::class));
    }

    public function includeCatalogs(ProductsPerspective $model)
    {
        $product = ProductsPerspectiveService::getBySlug($model->slug);

        $catalogs = ProductCatalogs::where('marketplace_product_id', $product->id)->get();

        return $this->collection($catalogs, app(ProductCatalogsTransformer::class));
    }

    public function includeMedia(ProductsPerspective $model)
    {
        $media = Media::withoutGlobalScope(AuthorizationScope::class)
            ->where('object_type', 'NextDeveloper\Marketplace\Database\Models\Products')
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($media, new MediaTransformer());
    }
}
