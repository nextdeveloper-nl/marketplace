<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Blogs\Database\Models\Posts;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductsTransformer;

/**
 * Class ProductsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductsTransformer extends AbstractProductsTransformer
{
    public array $availableIncludes = [
        'states',
        'actions',
        'media',
        'comments',
        'votes',
        'socialMedia',
        'phoneNumbers',
        'addresses',
        'meta',
        'blogs'
    ];

    /**
     * @param Products $model
     *
     * @return array
     */
    public function transform(Products $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Products', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Products', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }

    public function includeBlogs(Products $model)
    {
        $blogs = Posts::withoutGlobalScope(AuthorizationScope::class)
            ->where('tags', 'like', '%'.$model->slug.'%')
            ->take(3)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->collection($blogs, new \NextDeveloper\Blogs\Http\Transformers\PostsTransformer());
    }

    public function includeCatalogs()
    {

    }
}
