<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NextDeveloper\Commons\Common\Enums\GenericErrorCodes;
use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\Commons\Services\MediaService;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Products;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Product documents (leo4#1398): typed PDF attachments of a product, such as its catalog (brochure) and its
 * user manual.
 *
 * A document is an ordinary Commons media row on the product, in collection `documents`, tagged
 * `marketplace-product-document` plus its type, with the type also in custom_properties.document_type. No table of
 * its own. Same pattern as the identity documents of the platform app.
 *
 * Storage and visibility: the file goes to a filesystem disk as a private object (config
 * marketplace.documents.disk) and has no public address. It is read back by streaming it through download(), which
 * lets anyone who can see the product read it: the owner account always, and every signed-in customer when the
 * product is public. Writes are for the account that owns the product only.
 *
 * One current version per type: uploading a type the product already has replaces it. The previous file is
 * deleted from the disk and its row soft deleted (no version history).
 */
class ProductDocumentsService
{
    public const COLLECTION = 'documents';

    public const TAG = 'marketplace-product-document';

    public const TYPE_CATALOG = 'catalog';

    public const TYPE_USER_MANUAL = 'user_manual';

    /** @var array<int, string> */
    public const TYPES = [self::TYPE_CATALOG, self::TYPE_USER_MANUAL];

    public const MIME_TYPE = 'application/pdf';

    /** Storage names MediaService does not treat as a private disk (see disk()). */
    public const PUBLIC_STORAGE_KEYWORDS = ['', 'local', 'publitio'];

    /**
     * Documents of a product, one per type at most, in the order of TYPES.
     */
    public static function list(string $productRef): Collection
    {
        $product = self::product($productRef);

        return self::query($product)->get()
            ->sortBy(fn (Media $media) => array_search(self::typeOf($media), self::TYPES, true))
            ->values();
    }

    /**
     * Stores the PDF as the product's document of the given type, replacing the current one of that type.
     * The new file is stored first, so a failed upload never loses the document the product already had.
     */
    public static function upload(string $productRef, string $type, UploadedFile $file, ?string $name = null): Media
    {
        $product = self::product($productRef, true);

        if (! in_array($type, self::TYPES, true)) {
            self::refuse('type', 'The document type must be one of: '.implode(', ', self::TYPES).'.');
        }

        $disk = self::disk();
        $previous = self::query($product)->get()->filter(fn (Media $media) => self::typeOf($media) === $type);

        $media = MediaService::create([
            'file' => $file,
            'object_type' => Products::class,
            'object_id' => $product->id,
            'collection_name' => self::COLLECTION,
            'name' => $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'tags' => [self::TAG, $type],
            'storage' => $disk,
        ]);

        // MediaService::create() replaces custom_properties with the storage details; the type is added after.
        $media = Media::where('uuid', $media->uuid)->first();
        MediaService::update($media->uuid, [
            'custom_properties' => array_merge((array) $media->custom_properties, ['document_type' => $type]),
        ]);

        foreach ($previous as $old) {
            self::purge($old);
        }

        return Media::where('uuid', $media->uuid)->first();
    }

    /**
     * Removes one document of the product: the stored file and the row.
     */
    public static function remove(string $productRef, string $mediaId): void
    {
        $product = self::product($productRef, true);
        $media = self::query($product)->where('uuid', $mediaId)->first();

        if (! $media) {
            self::refuse('document_id', 'The document does not belong to this product.');
        }

        self::purge($media);
    }

    /**
     * Streams the file to anyone who can see the product.
     */
    public static function download(string $productRef, string $mediaId): StreamedResponse
    {
        $product = self::product($productRef);
        $media = self::query($product)->where('uuid', $mediaId)->first();

        if (! $media) {
            throw new NotFoundException('The document cannot be found.');
        }

        $path = self::storedPath($media);

        if (! $path || ! $media->disk || ! config('filesystems.disks.'.$media->disk) || ! Storage::disk($media->disk)->exists($path)) {
            throw new NotFoundException('The document file is missing.');
        }

        return Storage::disk($media->disk)->response($path, $media->file_name ?: 'document.pdf', [
            'Content-Type' => self::MIME_TYPE,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The product is going away: soft delete its document rows. Files stay, so a restored product could get them
     * back by hand; they are never reachable without the product.
     */
    public static function detachAll(Products $product): void
    {
        foreach (self::query($product)->get() as $media) {
            $media->delete();
        }
    }

    public static function typeOf(Media $media): ?string
    {
        $type = data_get($media->custom_properties, 'document_type');
        if (in_array($type, self::TYPES, true)) {
            return $type;
        }

        foreach ((array) $media->tags as $tag) {
            if (in_array($tag, self::TYPES, true)) {
                return $tag;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------------------------

    /**
     * Reads go through Products' AuthorizationScope: a product the caller cannot see is not found. Writes also
     * need the caller's account to own the product, as for images.
     */
    private static function product(string $ref, bool $forWrite = false): Products
    {
        $product = ProductsService::getByRef($ref);

        if (! $product) {
            throw new NotFoundException('The product cannot be found.');
        }

        if ($forWrite && (int) $product->iam_account_id !== (int) UserHelper::currentAccount()?->id) {
            throw new NotAllowedException('Only the account that owns this product can change its documents.');
        }

        return $product;
    }

    /**
     * Media rows are scoped to their owner account, so a customer reading a public product would not see them.
     * The product has already been authorised; the rows are pinned to the product's own account so a file
     * attached to the product by someone else through the generic media API never shows up.
     */
    private static function query(Products $product): Builder
    {
        return Media::withoutGlobalScopes()
            ->where('object_type', Products::class)
            ->where('object_id', $product->id)
            ->where('iam_account_id', $product->iam_account_id)
            ->where('collection_name', self::COLLECTION)
            ->whereNull('deleted_at')
            ->orderBy('id');
    }

    /**
     * The disk must be a real, configured filesystem disk. MediaService treats '', 'local' and 'publitio' as
     * storage keywords (the PUBLIC local disk and the public CDN) and falls back to the public local disk for an
     * unknown name, any of which would give the file a public address.
     */
    private static function disk(): string
    {
        $disk = (string) config('marketplace.documents.disk');

        if (in_array($disk, self::PUBLIC_STORAGE_KEYWORDS, true) || ! config('filesystems.disks.'.$disk)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Product documents cannot be stored on this installation yet: no storage is configured for them.',
                'code' => GenericErrorCodes::VALIDATION_FAILED,
            ], 503));
        }

        return $disk;
    }

    private static function purge(Media $media): void
    {
        $path = self::storedPath($media);

        if ($path && $media->disk && config('filesystems.disks.'.$media->disk)) {
            try {
                Storage::disk($media->disk)->delete($path);
            } catch (\Throwable $e) {
                // A storage hiccup may not keep the seller from replacing or removing a document.
                Log::warning(__METHOD__.' - could not delete document file', ['media' => $media->uuid, 'error' => $e->getMessage()]);
            }
        }

        $media->delete();
    }

    private static function storedPath(Media $media): ?string
    {
        return data_get($media->custom_properties, 'path');
    }

    private static function refuse(string $field, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'code' => GenericErrorCodes::VALIDATION_FAILED,
            'errors' => [$field => [$message]],
        ], 422));
    }
}
