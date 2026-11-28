<?php

namespace NextDeveloper\Marketplace\Http\Requests\ProductDocuments;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;
use NextDeveloper\Marketplace\Services\ProductDocumentsService;

/**
 * Upload of a product document (leo4#1398): a PDF of one of the known types.
 * The type is checked twice on purpose: by the content (mimetypes, read from the file itself) and by the
 * extension the content maps to (mimes), so a renamed non-PDF file is refused.
 */
class ProductDocumentsUploadRequest extends AbstractFormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:'.implode(',', ProductDocumentsService::TYPES),
            'file' => 'required|file|mimetypes:'.ProductDocumentsService::MIME_TYPE.'|mimes:pdf|max:'.self::maxKb(),
            'name' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The document type must be one of: '.implode(', ', ProductDocumentsService::TYPES).'.',
            'file.mimetypes' => 'Only PDF files can be attached as product documents.',
            'file.mimes' => 'Only PDF files can be attached as product documents.',
            'file.max' => 'A product document can be at most '.(int) round(self::maxKb() / 1024).' MB.',
        ];
    }

    public static function maxKb(): int
    {
        return max(1, (int) config('marketplace.documents.max_kb', 20480));
    }
}
