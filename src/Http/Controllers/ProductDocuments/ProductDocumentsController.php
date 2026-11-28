<?php

namespace NextDeveloper\Marketplace\Http\Controllers\ProductDocuments;

use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Http\Transformers\MediaTransformer;
use NextDeveloper\Marketplace\Http\Controllers\AbstractController;
use NextDeveloper\Marketplace\Http\Requests\ProductDocuments\ProductDocumentsUploadRequest;
use NextDeveloper\Marketplace\Services\ProductDocumentsService;

/**
 * Product documents (leo4#1398): catalog and user-manual PDFs. Delegates to ProductDocumentsService.
 */
class ProductDocumentsController extends AbstractController
{
    /** GET /marketplace/products/{product}/documents */
    public function index($ref)
    {
        return response()->json([
            'data' => ProductDocumentsService::list($ref)->map(fn ($m) => $this->transform($m))->values(),
        ]);
    }

    /**
     * POST /marketplace/products/{product}/documents  (multipart)
     * Body: `type` (catalog | user_manual), `file` (PDF), optional `name`. Replaces the product's document of that type.
     */
    public function store($ref, ProductDocumentsUploadRequest $request)
    {
        $data = $request->validated();

        $media = ProductDocumentsService::upload($ref, $data['type'], $request->file('file'), $data['name'] ?? null);

        return response()->json(['data' => $this->transform($media)], 201);
    }

    /** GET /marketplace/products/{product}/documents/{document}/download */
    public function download($ref, $documentId)
    {
        return ProductDocumentsService::download($ref, $documentId);
    }

    /** DELETE /marketplace/products/{product}/documents/{document} */
    public function destroy($ref, $documentId)
    {
        ProductDocumentsService::remove($ref, $documentId);

        return response()->json(null, 204);
    }

    /**
     * The media payload plus the document type. The internal storage key is left out: the file is only read
     * through the download endpoint.
     *
     * @return array<string, mixed>
     */
    private function transform(Media $media): array
    {
        $transformed = (new MediaTransformer)->transform($media);
        $transformed['document_type'] = ProductDocumentsService::typeOf($media);
        unset($transformed['custom_properties'], $transformed['disk'], $transformed['manipulations']);

        return $transformed;
    }
}
