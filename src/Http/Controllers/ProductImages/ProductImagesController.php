<?php

namespace NextDeveloper\Marketplace\Http\Controllers\ProductImages;

use Illuminate\Http\Request;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Commons\Http\Transformers\MediaTransformer;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Marketplace\Services\ProductImagesService;

/**
 * Product images (references to files on a URL or in the customer's own S3 bucket).
 *
 * Added: controller only validates the request and delegates to ProductImagesService.
 */
class ProductImagesController extends AbstractController
{
    /** GET /marketplace/products/{product}/images */
    public function index($ref)
    {
        return $this->collection(ProductImagesService::list($ref));
    }

    /**
     * POST /marketplace/products/{product}/images
     * Body: either `url`, or `bucket_id` + `object_key`. Optional `alt`, `is_cover`.
     */
    public function store($ref, Request $request)
    {
        $data = $request->validate([
            'url' => 'required_without:bucket_id|nullable|url|max:2048',
            'bucket_id' => 'required_without:url|nullable|uuid',
            'object_key' => 'required_with:bucket_id|nullable|string|max:1024',
            'alt' => 'nullable|string|max:255',
            'is_cover' => 'nullable|boolean',
        ]);

        $media = isset($data['bucket_id'])
            ? ProductImagesService::addFromS3($ref, $data['bucket_id'], $data['object_key'], $data['alt'] ?? null, (bool) ($data['is_cover'] ?? false))
            : ProductImagesService::addFromUrl($ref, $data['url'], $data['alt'] ?? null, (bool) ($data['is_cover'] ?? false));

        return $this->item($media, 201);
    }

    /**
     * POST /marketplace/products/{product}/images/import
     * Body: `urls` - a list of URLs, or of {url, alt} objects.
     */
    public function import($ref, Request $request)
    {
        $data = $request->validate([
            'urls' => 'required|array|min:1|max:'.ProductImagesService::MAX_IMAGES,
            'urls.*' => 'required',
            'urls.*.url' => 'sometimes|url|max:2048',
        ]);

        return response()->json(ProductImagesService::importFromUrls($ref, $data['urls']), 200);
    }

    /** PUT /marketplace/products/{product}/images/order  Body: `order` - list of image ids. */
    public function reorder($ref, Request $request)
    {
        $data = $request->validate(['order' => 'required|array|min:1', 'order.*' => 'required|uuid']);

        return $this->collection(ProductImagesService::reorder($ref, $data['order']));
    }

    /** POST /marketplace/products/{product}/images/{image}/cover */
    public function cover($ref, $imageId)
    {
        return $this->collection(ProductImagesService::setCover($ref, $imageId));
    }

    /** DELETE /marketplace/products/{product}/images/{image} */
    public function destroy($ref, $imageId)
    {
        ProductImagesService::remove($ref, $imageId);

        return response()->json(null, 204);
    }

    private function collection($models)
    {
        return response()->json(['data' => $models->map(fn ($m) => (new MediaTransformer())->transform($m))->values()]);
    }

    private function item($model, int $status = 200)
    {
        return response()->json(['data' => (new MediaTransformer())->transform($model)], $status);
    }
}
