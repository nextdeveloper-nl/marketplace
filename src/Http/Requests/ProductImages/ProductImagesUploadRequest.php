<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductImages;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Services\ProductImagesService;

/**
 * Upload of a product image file (leo4#1398). JPEG, PNG, WebP or GIF only, checked by the file's content; SVG is
 * not accepted because it can carry script.
 */
class ProductImagesUploadRequest extends AbstractFormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimetypes:'.implode(',', ProductImagesService::UPLOAD_MIME_TYPES).'|max:'.self::maxKb(),
            'alt' => 'nullable|string|max:255',
            'is_cover' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Only JPEG, PNG, WebP or GIF images can be uploaded.',
            'file.max' => 'An image can be at most '.(int) round(self::maxKb() / 1024).' MB.',
        ];
    }

    public static function maxKb(): int
    {
        return max(1, (int) config('marketplace.images.max_kb', 10240));
    }
}
