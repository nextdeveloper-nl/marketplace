<?php

namespace NextDeveloper\Marketplace\Helpers;

use Illuminate\Http\Exceptions\HttpResponseException;
use NextDeveloper\Commons\Common\Enums\GenericErrorCodes;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Services\ProductImagesService;

/**
 * leo4#1398: a product needs at least one image before it is made public.
 *
 * The rule bites only on the change to public: creating a product as public, or switching a private product to
 * public. A product that is public already can still be edited without an image (most public products have none
 * today), and a draft (private) product can be created and edited freely, so "create with a price" keeps working.
 * Enforced on the API requests only: the platform's own sync jobs create public products through the service and
 * are not affected.
 */
final class ProductPublishing
{
    public const CREATE_MESSAGE = 'A product needs at least one image before it can be public. Create it without "Public", add an image on the product page, then make it public.';

    public const UPDATE_MESSAGE = 'Add at least one image to this product before making it public.';

    /**
     * Whether the request turns a product public. $currentlyPublic is false for a product being created.
     */
    public static function turnsPublic(bool $currentlyPublic, mixed $requestedIsPublic): bool
    {
        return ! $currentlyPublic
            && filter_var($requestedIsPublic, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /**
     * @param  Products|null  $product  null when the product is being created
     *
     * @throws HttpResponseException 422 with the reason as the message, so the panel's toast shows it as it is
     */
    public static function assertCanTurnPublic(?Products $product, mixed $requestedIsPublic): void
    {
        if (! self::turnsPublic((bool) $product?->is_public, $requestedIsPublic)) {
            return;
        }

        if ($product && ProductImagesService::hasImage($product)) {
            return;
        }

        $message = $product ? self::UPDATE_MESSAGE : self::CREATE_MESSAGE;

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'code' => GenericErrorCodes::VALIDATION_FAILED,
            'errors' => ['is_public' => [$message]],
        ], 422));
    }
}
