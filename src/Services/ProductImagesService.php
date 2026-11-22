<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Commons\Common\Enums\GenericErrorCodes;
use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Services\MediaService;
use NextDeveloper\IAM\Database\Models\Accounts;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Products;

/**
 * Product images.
 *
 * Images are ordinary Commons media rows attached to the product (collection `images`); this service is the
 * Marketplace-level API over them: add, import, reorder, cover and remove.
 *
 * Storage policy: the platform never copies or re-hosts a product image. A row is a REFERENCE to a file that
 * lives somewhere else, in one of two forms:
 *  - `url` : any public http(s) address (a CDN, a merchant's shop, an imported marketplace listing).
 *  - `s3`  : an object in a bucket the caller owns on the platform S3 service. If the customer wants the platform
 *            to hold their image, they open a bucket, upload the file there and register it by bucket + key.
 *
 * Added: new service, so image handling is not left to clients that would otherwise have to know the Commons
 * media route and the Products model class name.
 */
class ProductImagesService
{
    public const COLLECTION = 'images';

    public const SOURCE_URL = 'url';
    public const SOURCE_S3 = 's3';
    public const SOURCE_PROVIDER = 'provider';

    /** Hard cap so a runaway import cannot attach thousands of rows to one product. */
    public const MAX_IMAGES = 50;

    /**
     * Images of a product, cover first, then by order.
     */
    public static function list(string $productRef): Collection
    {
        return self::query(self::product($productRef))->get();
    }

    /**
     * Public URLs of the product's images in display order (cover first). This is what listings pushed to a
     * marketplace are built from, so the media rows are the single source of truth for images.
     * Only media owned by the product's own account counts: the generic media API lets anyone who can see a public
     * product attach a file to it, and such files must never reach a listing. Not filtered by the caller's account
     * because pushes also run from queue jobs.
     *
     * @return array<int, string>
     */
    public static function urlsFor(Products $product): array
    {
        return Media::withoutGlobalScopes()
            ->where('object_type', Products::class)
            ->where('object_id', $product->id)
            ->where('iam_account_id', $product->iam_account_id)
            ->where('collection_name', self::COLLECTION)
            ->whereNull('deleted_at')
            ->whereNotNull('cdn_url')
            ->orderBy('order_column')
            ->orderBy('id')
            ->pluck('cdn_url')
            ->all();
    }

    /**
     * Cover image address for list views, so a client does not need include=media just for a thumbnail.
     * Null when the product has no image. Files are referenced, not hosted, so there are no resized variants:
     * the URL is the customer's own (their CDN or S3 bucket) and is served as it is.
     */
    public static function coverUrlFor(int $productId, int $accountId): ?string
    {
        $rows = Media::withoutGlobalScopes()
            ->where('object_type', Products::class)
            ->where('object_id', $productId)
            ->where('iam_account_id', $accountId)
            ->where('collection_name', self::COLLECTION)
            ->whereNull('deleted_at')
            ->whereNotNull('cdn_url')
            ->orderBy('order_column')
            ->orderBy('id')
            ->get(['cdn_url', 'custom_properties']);

        $cover = $rows->first(fn ($m) => (bool) data_get($m->custom_properties, 'is_cover')) ?? $rows->first();

        return $cover?->cdn_url;
    }

    /**
     * The product is going away: detach its image references. Files are never deleted - they belong to the
     * customer's CDN or bucket.
     */
    public static function detachAll(Products $product): void
    {
        foreach (self::query($product)->get() as $media) {
            MediaService::delete($media->uuid);
        }
    }

    /**
     * Registers one public URL as an image of the product. The file is not fetched or copied.
     * Registering the same URL twice returns the existing row.
     */
    public static function addFromUrl(string $productRef, string $url, ?string $alt = null, bool $asCover = false): Media
    {
        $product = self::product($productRef, true);

        return self::register($product, self::assertUrl($url), self::SOURCE_URL, ['alt' => $alt], $asCover);
    }

    /**
     * Imports many URLs at once. A bad or duplicate URL does not abort the others; the outcome per URL is returned.
     *
     * @param  array<int, string|array{url: string, alt?: string}>  $items
     * @return array{added: int, skipped: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public static function importFromUrls(string $productRef, array $items): array
    {
        $product = self::product($productRef, true);
        $summary = ['added' => 0, 'skipped' => 0, 'failed' => 0, 'results' => []];

        foreach ($items as $item) {
            $url = is_array($item) ? ($item['url'] ?? '') : (string) $item;
            $alt = is_array($item) ? ($item['alt'] ?? null) : null;

            try {
                $existed = self::findByUrl($product, (string) $url) !== null;
                $media = self::register($product, self::assertUrl((string) $url), self::SOURCE_URL, ['alt' => $alt]);

                $summary[$existed ? 'skipped' : 'added']++;
                $summary['results'][] = ['url' => $url, 'status' => $existed ? 'skipped' : 'added', 'id' => $media->uuid];
            } catch (HttpResponseException $e) {
                $summary['failed']++;
                $summary['results'][] = [
                    'url' => $url,
                    'status' => 'failed',
                    'error' => data_get($e->getResponse()->getData(true), 'errors', 'rejected'),
                ];
            }
        }

        return $summary;
    }

    /**
     * Registers an object from one of the caller's own S3 buckets as an image.
     *
     * The bucket is resolved through the S3 module's own authorization scope, so a bucket of another account is
     * "not found". The address is built from the bucket's server and name; the object itself is not checked here,
     * the bucket (or the object) must be publicly readable for marketplaces to be able to fetch it.
     */
    public static function addFromS3(string $productRef, string $bucketRef, string $objectKey, ?string $alt = null, bool $asCover = false): Media
    {
        $product = self::product($productRef, true);
        $bucketModel = 'NextDeveloper\\S3\\Database\\Models\\BucketsPerspective';

        if (! class_exists($bucketModel)) {
            self::refuse('bucket_id', 'The S3 service is not available on this installation.');
        }

        $objectKey = ltrim(trim($objectKey), '/');
        if ($objectKey === '' || str_contains($objectKey, '..')) {
            self::refuse('object_key', 'object_key must be the path of the file inside the bucket.');
        }

        $bucket = $bucketModel::where('uuid', $bucketRef)->first();
        if (! $bucket) {
            self::refuse('bucket_id', 'The bucket cannot be found. Open an S3 bucket in your account and upload the image there first.');
        }

        $name = $bucket->bucket_name ?: $bucket->name;
        $url = 'https://'.$bucket->s3_server_hostname.'/'.$name.'/'.implode('/', array_map('rawurlencode', explode('/', $objectKey)));

        return self::register($product, $url, self::SOURCE_S3, [
            'alt' => $alt,
            's3_bucket_id' => $bucket->uuid,
            'object_key' => $objectKey,
        ], $asCover);
    }

    /**
     * Makes the product's image set match what a marketplace listing has, without copying any file.
     * Used by the Shopify / Ideasoft / eBay importers. Rows of source `provider` that the listing no longer
     * carries are removed; rows the customer added themselves (url / s3) are never touched.
     *
     * @param  array<int, array{url?: string, alt?: string|null}>  $images
     */
    public static function syncProviderImages(Products $product, string $providerKey, array $images): void
    {
        // Importers run in queue jobs without a signed-in user: act as the platform, but give the media rows to the
        // product's owner so they show up in the owner's account like any image they added by hand.
        UserHelper::runAsAdmin(function () use ($product, $providerKey, $images) {
            self::syncProviderImagesAsAdmin($product, $providerKey, $images);
        });
    }

    private static function syncProviderImagesAsAdmin(Products $product, string $providerKey, array $images): void
    {
        $account = Accounts::withoutGlobalScopes()->find($product->iam_account_id);
        $user = Users::withoutGlobalScopes()->find($product->iam_user_id);
        // Passed explicitly down to register(); no static state that could leak into the next call.
        $owner = array_filter([
            'iam_account_id' => $account?->uuid,
            'iam_user_id' => $user?->uuid,
        ]);

        $wanted = [];
        foreach ($images as $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : $image;
            if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $wanted[$url] = is_array($image) ? ($image['alt'] ?? null) : null;
        }

        $current = self::query($product)->get();

        foreach ($current as $media) {
            $props = (array) $media->custom_properties;
            if (($props['source'] ?? null) === self::SOURCE_PROVIDER
                && ($props['provider'] ?? null) === $providerKey
                && ! array_key_exists($media->cdn_url, $wanted)) {
                MediaService::delete($media->uuid);
            }
        }

        foreach ($wanted as $url => $alt) {
            if (self::findByUrl($product, $url)) {
                continue;
            }
            self::register($product, $url, self::SOURCE_PROVIDER, ['alt' => $alt, 'provider' => $providerKey], false, false, $owner);
        }

        self::ensureCover($product);
        self::forget($product);
    }

    /**
     * Sets the display order. $orderedIds are media uuids; images not listed keep their relative order after them.
     *
     * @param  array<int, string>  $orderedIds
     */
    public static function reorder(string $productRef, array $orderedIds): Collection
    {
        $product = self::product($productRef, true);
        $images = self::query($product)->get()->keyBy('uuid');

        foreach ($orderedIds as $id) {
            if (! $images->has($id)) {
                self::refuse('order', "Image {$id} does not belong to this product.");
            }
        }

        $rest = $images->keys()->diff($orderedIds)->values()->all();
        foreach (array_merge(array_values($orderedIds), $rest) as $position => $id) {
            if ((int) $images[$id]->order_column !== $position) {
                MediaService::update($id, ['order_column' => $position]);
            }
        }

        self::forget($product);

        return self::query($product)->get();
    }

    /**
     * Marks one image as the cover and moves it to the first position.
     */
    public static function setCover(string $productRef, string $mediaId): Collection
    {
        $product = self::product($productRef, true);
        $images = self::query($product)->get();

        if (! $images->firstWhere('uuid', $mediaId)) {
            self::refuse('image_id', 'The image does not belong to this product.');
        }

        foreach ($images as $media) {
            $props = (array) $media->custom_properties;
            $isCover = $media->uuid === $mediaId;

            if ((bool) ($props['is_cover'] ?? false) !== $isCover) {
                MediaService::update($media->uuid, ['custom_properties' => array_merge($props, ['is_cover' => $isCover])]);
            }
        }

        return self::reorder($productRef, array_merge([$mediaId], $images->pluck('uuid')->reject(fn ($u) => $u === $mediaId)->values()->all()));
    }

    /**
     * Detaches an image from the product. Only the reference goes: the file stays where it is (it is the
     * customer's, on their CDN or in their bucket). If the cover was removed the first remaining image becomes it.
     */
    public static function remove(string $productRef, string $mediaId): void
    {
        $product = self::product($productRef, true);
        $media = self::query($product)->where('uuid', $mediaId)->first();

        if (! $media) {
            self::refuse('image_id', 'The image does not belong to this product.');
        }

        MediaService::delete($media->uuid);

        self::ensureCover($product);
        self::forget($product);
    }

    // ------------------------------------------------------------------------------------------------------------

    /**
     * @param  bool  $forWrite  Writes need more than visibility: a public product is visible to everyone, but only
     *                          the account that owns it may change its images.
     */
    private static function product(string $ref, bool $forWrite = false): Products
    {
        // Goes through Products' AuthorizationScope: a product the caller cannot see is not found.
        $product = ProductsService::getByRef($ref);

        if (! $product) {
            self::refuse('product_id', 'The product cannot be found.');
        }

        if ($forWrite && (int) $product->iam_account_id !== (int) UserHelper::currentAccount()?->id) {
            throw new NotAllowedException('Only the account that owns this product can change its images.');
        }

        return $product;
    }

    private static function query(Products $product)
    {
        return Media::where('object_type', Products::class)
            ->where('object_id', $product->id)
            ->where('iam_account_id', $product->iam_account_id)
            ->where('collection_name', self::COLLECTION)
            ->orderBy('order_column')
            ->orderBy('id');
    }

    private static function findByUrl(Products $product, string $url): ?Media
    {
        return self::query($product)->where('cdn_url', $url)->first();
    }

    /**
     * Creates the reference row, or returns the existing one for the same URL.
     */
    private static function register(Products $product, string $url, string $source, array $props, bool $asCover = false, bool $refresh = true, array $owner = []): Media
    {
        if ($existing = self::findByUrl($product, $url)) {
            return $existing;
        }

        $count = self::query($product)->count();
        if ($count >= self::MAX_IMAGES) {
            self::refuse('url', 'A product can have at most '.self::MAX_IMAGES.' images.');
        }

        $media = MediaService::create($owner + [
            'object_type' => Products::class,
            'object_id' => $product->uuid,
            'collection_name' => self::COLLECTION,
            'name' => $props['alt'] ?? null,
            'cdn_url' => $url,
            'mime_type' => self::guessMime($url),
            'disk' => $source,
            'order_column' => $count,
            'custom_properties' => array_merge(array_filter($props, fn ($v) => $v !== null), [
                'source' => $source,
                'is_cover' => false,
            ]),
        ]);

        if ($asCover || $count === 0) {
            $media = Media::where('uuid', $media->uuid)->first();
            MediaService::update($media->uuid, [
                'custom_properties' => array_merge((array) $media->custom_properties, ['is_cover' => true]),
            ]);
            if ($asCover && $count > 0) {
                self::setCover($product->uuid, $media->uuid);
            }
        }

        if ($refresh) {
            self::forget($product);
        }

        return Media::where('uuid', $media->uuid)->first();
    }

    /** Guarantees exactly one cover whenever the product has images. */
    private static function ensureCover(Products $product): void
    {
        $images = self::query($product)->get();

        if ($images->isEmpty() || $images->contains(fn ($m) => (bool) data_get($m->custom_properties, 'is_cover'))) {
            return;
        }

        $first = $images->first();
        MediaService::update($first->uuid, [
            'custom_properties' => array_merge((array) $first->custom_properties, ['is_cover' => true]),
        ]);
    }

    /** The transformed product is cached; the cover and media includes must show the change. */
    private static function forget(Products $product): void
    {
        CacheHelper::deleteKeys(Products::class, $product->uuid);
    }

    /**
     * Only public http(s) addresses are accepted. The platform does not fetch them, but marketplaces will, and a
     * private or loopback address in a listing is never intended.
     */
    private static function assertUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || $host === '') {
            self::refuse('url', 'The image address must be a valid http(s) URL.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || (filter_var($host, FILTER_VALIDATE_IP)
                && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            self::refuse('url', 'The image address must be publicly reachable.');
        }

        return $url;
    }

    private static function guessMime(string $url): ?string
    {
        return match (strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => null,
        };
    }

    private static function refuse(string $field, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed. Please fix the values you are providing and try again.',
            'code' => GenericErrorCodes::VALIDATION_FAILED,
            'errors' => [$field => [$message]],
        ], 422));
    }
}
