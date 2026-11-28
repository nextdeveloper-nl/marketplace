<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Validation\ValidationException;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductRelations;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Helpers\ProductOfferings;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductRelationsService;
use Override;

/**
 * This class is responsible from managing the data for ProductRelations
 *
 * Class ProductRelationsService.
 */
class ProductRelationsService extends AbstractProductRelationsService
{
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Links an accessory, spare part or bundled item to a main product (leo4#1397).
     *
     * The main product must belong to the user's account: a family is the seller's own catalog structure. The item
     * only has to be visible to the user (their own product, or one on a public market), so a reseller can list a
     * vendor's battery as an accessory of the robot they sell. The link belongs to the main product's account.
     *
     * @param  array{marketplace_product_id: string, related_marketplace_product_id: string, relation_type: string}  $data
     *
     * @throws ValidationException when the type is unknown, a product cannot be found, both ends are the same
     *                             product, or the same link already exists
     * @throws NotAllowedException when the main product belongs to another account
     */
    #[Override]
    public static function create(array $data)
    {
        $relationType = self::assertRelationType($data['relation_type'] ?? null);

        $product = self::visibleProduct($data['marketplace_product_id'] ?? null, 'marketplace_product_id');
        $relatedProduct = self::visibleProduct($data['related_marketplace_product_id'] ?? null, 'related_marketplace_product_id');

        if ((int) $product->id === (int) $relatedProduct->id) {
            throw ValidationException::withMessages([
                'related_marketplace_product_id' => 'A product cannot be an accessory or spare part of itself.',
            ]);
        }

        if ((int) $product->iam_account_id !== (int) UserHelper::currentAccount()?->id) {
            throw new NotAllowedException('Only the account that owns the main product can change its family.');
        }

        self::assertNotLinked((int) $product->id, (int) $relatedProduct->id, $relationType);

        return parent::create([
            'marketplace_product_id' => (int) $product->id,
            'related_marketplace_product_id' => (int) $relatedProduct->id,
            'relation_type' => $relationType,
            'iam_account_id' => (int) $product->iam_account_id,
        ]);
    }

    /**
     * Only the relation type can change. The product ids are dropped: moving an item to another family is a delete
     * plus a new link.
     *
     * @param  array{relation_type?: string}  $data
     */
    #[Override]
    public static function update($id, array $data)
    {
        $data = array_intersect_key($data, ['relation_type' => true]);

        if (array_key_exists('relation_type', $data)) {
            $data['relation_type'] = self::assertRelationType($data['relation_type']);

            $relation = ProductRelations::query()->where('uuid', $id)->first();

            if ($relation && $relation->relation_type !== $data['relation_type']) {
                self::assertNotLinked(
                    (int) $relation->marketplace_product_id,
                    (int) $relation->related_marketplace_product_id,
                    $data['relation_type'],
                );
            }
        }

        return parent::update($id, $data);
    }

    /**
     * @throws ValidationException
     */
    private static function assertRelationType(mixed $relationType): string
    {
        if (! is_string($relationType) || ! in_array($relationType, ProductOfferings::RELATION_TYPES, true)) {
            throw ValidationException::withMessages([
                'relation_type' => 'The relation type must be one of: '.implode(', ', ProductOfferings::RELATION_TYPES).'.',
            ]);
        }

        return $relationType;
    }

    /**
     * Scoped read: the product must be visible to the user. A uuid that does not resolve is a 422, never a silent null.
     *
     * @throws ValidationException
     */
    private static function visibleProduct(mixed $uuid, string $field): Products
    {
        $product = is_string($uuid) && $uuid !== ''
            ? Products::query()->withoutGlobalScope(LimitScope::class)->where('uuid', $uuid)->first()
            : null;

        if (! $product) {
            throw ValidationException::withMessages([$field => 'The product could not be found.']);
        }

        return $product;
    }

    /**
     * The unique index marketplace_product_relations_unique_live allows one live link per (main product, item, type).
     * Checked here first so a duplicate is a 422 and not a SQL error.
     *
     * @throws ValidationException
     */
    private static function assertNotLinked(int $productId, int $relatedProductId, string $relationType): void
    {
        $exists = ProductRelations::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('marketplace_product_id', $productId)
            ->where('related_marketplace_product_id', $relatedProductId)
            ->where('relation_type', $relationType)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'related_marketplace_product_id' => 'This product is already linked to the family as '.$relationType.'.',
            ]);
        }
    }
}
