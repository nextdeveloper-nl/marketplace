<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Database\Models\Categories;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Filters\ProductsQueryFilter;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductsService;

/**
 * This class is responsible from managing the data for Products
 *
 * Class ProductsService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProductsService extends AbstractProductsService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Added: a product may only use a category the caller can see (public, or owned by the current account).
     * Categories are tenant owned now, so another tenant's private category must be rejected loudly instead of
     * being silently dropped when the uuid fails to resolve.
     */
    public static function create(array $data)
    {
        self::assertCategoryUsable($data['common_category_id'] ?? null);

        $catalog = $data['catalog'] ?? null;
        unset($data['catalog']);

        // Added (leo4#1394): the API never accepted a slug, so products created from the panel had none. The
        // marketplace syncs rely on it, so it is generated the way the sync imports do (name slug + short suffix).
        if (empty($data['slug'])) {
            $data['slug'] = self::generateSlug((string) ($data['name'] ?? ''));
        }

        if (! is_array($catalog) || ! isset($catalog['price'])) {
            return parent::create($data);
        }

        // Added (leo4#1394): "create with price" makes the product and its first plan together, or neither.
        return DB::transaction(function () use ($data, $catalog) {
            $product = parent::create($data);

            self::createFirstCatalog($product, $catalog);

            return $product->fresh();
        });
    }

    /**
     * Platform-wide slugs are not unique on their own, so the name slug gets a random 6-hex suffix.
     */
    public static function generateSlug(string $name): string
    {
        return (Str::slug($name) ?: 'product').'-'.bin2hex(random_bytes(3));
    }

    /**
     * The plan "Add Plan / Item" would make: named after the product, priced in the given currency or,
     * when none is given, in the market's currency (the currency the catalog price is documented to use).
     *
     * @param  array{price: int|float|string, sku?: string|null, common_currency_id?: string|null}  $catalog
     */
    private static function createFirstCatalog(Products $product, array $catalog): void
    {
        $currency = $catalog['common_currency_id'] ?? null;

        if (! $currency) {
            $currency = Markets::withoutGlobalScope(AuthorizationScope::class)
                ->where('id', $product->marketplace_market_id)
                ->value('common_currency_id');
        }

        ProductCatalogsService::create([
            'name' => $product->name,
            'price' => $catalog['price'],
            'sku' => $catalog['sku'] ?? null,
            'common_currency_id' => $currency,
            'marketplace_product_id' => $product->id,
            'iam_account_id' => $product->iam_account_id,
            'iam_user_id' => $product->iam_user_id,
        ]);
    }

    /**
     * Added: same category visibility rule on update.
     */
    public static function update($id, array $data)
    {
        self::assertCategoryUsable($data['common_category_id'] ?? null);

        return parent::update($id, $data);
    }

    /**
     * Goes through Categories' AuthorizationScope on purpose: categories the caller cannot see are not found.
     */
    private static function assertCategoryUsable(?string $categoryUuid): void
    {
        if (!$categoryUuid) {
            return;
        }

        if (!Categories::where('uuid', $categoryUuid)->exists()) {
            throw new NotAllowedException('The category cannot be found or you are not allowed to use it.');
        }
    }

    public static function get(?ProductsQueryFilter $filter = null, array $params = []): \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return parent::get($filter, $params);
    }

    /**
     * Added: two-way sync of one eBay-linked product (the product page "Sync with eBay" action).
     * Looks the product up through the scoped model, so users can only sync products they can see,
     * and requires update rights on it because the sync can change it.
     *
     * @param  string  $ref  Product uuid (public id)
     * @param  string|null  $providerRef  eBay provider uuid, needed only when the product has several
     * @return array<string, mixed>
     */
    public static function syncWithEbay(string $ref, ?string $providerRef = null, bool $dryRun = false, ?string $prefer = null): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\EbayProductSyncService::forProduct($product, $providerRef)
            ->sync($dryRun, $prefer);
    }
    /**
     * Added: link an existing local product to eBay (by SKU, or by creating the listing) so that
     * "Sync with eBay" works for it. Same lookup and permission rules as syncWithEbay().
     *
     * @param  array<string, mixed>  $options  provider_id, sku, create_listing, dry_run, category_id, aspects, image_urls, condition
     * @return array<string, mixed>
     */
    public static function linkToEbay(string $ref, array $options = []): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\EbayLinkService::forProduct($product, $options['provider_id'] ?? null)
            ->link($options);
    }

    /**
     * Added: link an existing local product (and its catalog entries) to the product with the same
     * SKUs on a connected Shopify shop. Same lookup and permission rules as linkToEbay().
     *
     * @param  array<string, mixed>  $options  provider_id, sku, dry_run
     * @return array<string, mixed>
     */
    public static function linkToShopify(string $ref, array $options = []): array
    {
        $product = self::getByRef($ref);

        if (! $product) {
            throw new \NextDeveloper\Commons\Exceptions\NotFoundException('Product not found.');
        }

        // Added: the account-owner gate, not the generic update policy (see ExternalSyncGate).
        \NextDeveloper\Marketplace\Services\Marketplaces\ExternalSyncGate::assertCanManage($product);

        return \NextDeveloper\Marketplace\Services\Marketplaces\ShopifyLinkService::forProduct($product, $options['provider_id'] ?? null)
            ->link($options);
    }
}
