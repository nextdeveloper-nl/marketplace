<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Database\Models\Currencies;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayClient;

/**
 * Links an EXISTING local product to eBay, either to a listing that already exists (match by SKU)
 * or by creating the listing from the local product.
 *
 * Linking is what makes a product eligible for the one-product sync (EbayProductSyncService): it
 * creates the product mapping and one catalog mapping per local catalog entry. It deliberately does
 * not baseline the sync hashes, so the first sync reports any difference between the local product
 * and the eBay listing as a conflict to settle instead of overwriting one side.
 *
 * Match mode (create_listing = false): every catalog entry's SKU must exist as an eBay inventory item;
 * if any is missing nothing is linked and the missing SKUs are named. Several catalog entries must
 * belong to one eBay variation group, because one local product maps to one eBay product.
 *
 * Create mode (create_listing = true): creates the inventory item and offer from the local product and
 * publishes it. This starts a LIVE eBay listing (fees in production), so it is limited to a single
 * catalog entry, needs the seller's saved setup (policies, location) and refuses anything it cannot
 * fill in with certainty (category, required item aspects, image, price, stock) rather than guess. A
 * dry run shows exactly what would be sent. Afterwards the product is baselined with a normal sync.
 */
class EbayLinkService
{
    private const STATUS_LINKED = 'linked';

    private const STATUS_CREATED = 'listing_created';

    private const STATUS_DRY_RUN = 'dry_run';

    private const STATUS_ALREADY = 'already_linked';

    private const STATUS_NOT_FOUND = 'not_found';

    private const STATUS_BLOCKED = 'blocked';

    private const MAX_TITLE_LENGTH = 80;

    private EbayService $service;

    public function __construct(private readonly Products $product, private readonly Providers $provider)
    {
        $this->service = new EbayService($provider);
    }

    /**
     * Pick the eBay provider to link against: same account as the product, active, optionally named.
     *
     * @throws NotFoundException When the account has no usable eBay connection.
     * @throws NotAllowedException When several qualify and none was named.
     */
    public static function forProduct(Products $product, ?string $providerRef = null): self
    {
        $providers = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw("lower(trim(adapter)) = 'ebay'")
            ->where('iam_account_id', $product->iam_account_id)
            ->where('is_active', true)
            ->when($providerRef, fn ($q) => $q->where('uuid', $providerRef))
            ->get()
            // A connection that never finished consent has no token and nothing to link against.
            ->filter(fn (Providers $p) => (string) $p->getDecryptedAccessToken() !== '')
            ->values();

        if ($providers->isEmpty()) {
            throw new NotFoundException('No connected eBay account found for this product. Connect eBay first.');
        }

        if ($providers->count() > 1) {
            throw new NotAllowedException('Several eBay accounts are connected. Say which one with provider_id.');
        }

        return new self($product, $providers->first());
    }

    /**
     * @param  array<string, mixed>  $options  sku, create_listing, dry_run, category_id, aspects, image_urls, condition
     * @return array<string, mixed> status = linked | listing_created | already_linked | not_found | blocked | dry_run
     */
    public function link(array $options = []): array
    {
        $lock = Cache::lock('marketplace:ebay:link:'.$this->provider->id.':'.$this->product->id, 120);

        if (! $lock->get()) {
            throw new NotAllowedException('This product is already being linked.');
        }

        try {
            // The writes are the link's own bookkeeping, not an action the requesting user's roles cover.
            return UserHelper::withRolesCheckBypassed(fn () => $this->run($options));
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private function run(array $o): array
    {
        $dryRun = (bool) ($o['dry_run'] ?? false);
        $create = (bool) ($o['create_listing'] ?? false);

        $existing = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('marketplace_product_id', $this->product->id)
            ->first();

        if ($existing) {
            return $this->result(self::STATUS_ALREADY, 'This product is already linked to eBay. Use "Sync with eBay".');
        }

        $catalogs = $this->catalogs();

        if ($catalogs->isEmpty()) {
            return $this->result(self::STATUS_BLOCKED, 'The product has no catalog entries, so there is nothing to link.');
        }

        // --- which SKU belongs to which catalog entry ------------------------------------------
        $skus = [];
        foreach ($catalogs as $catalog) {
            $sku = trim((string) $catalog->sku);

            if (! empty($o['sku'])) {
                if ($catalogs->count() > 1) {
                    return $this->result(self::STATUS_BLOCKED, 'A single sku can only be given for a product with one catalog entry; this one has '.$catalogs->count().'.');
                }
                $sku = trim((string) $o['sku']);
            }

            if ($sku === '') {
                return $this->result(self::STATUS_BLOCKED, 'Catalog entry "'.$catalog->name.'" has no SKU. Set one, or pass sku.');
            }

            $skus[$catalog->id] = $sku;
        }

        if (count(array_unique($skus)) !== count($skus)) {
            return $this->result(self::STATUS_BLOCKED, 'Two catalog entries share the same SKU, so they cannot be told apart on eBay.');
        }

        // --- what eBay has for them -------------------------------------------------------------
        $adapter = $this->service->getAdapter();
        $remote = [];
        $missing = [];

        foreach ($skus as $catalogId => $sku) {
            $item = $adapter->fetchItem($sku);

            if ($item === null) {
                $missing[] = $sku;

                continue;
            }

            $item['sku'] ??= $sku;
            $remote[$sku] = ['item' => $item, 'offers' => $adapter->fetchOffers($sku)];
        }

        $found = array_map(fn ($sku) => ['sku' => $sku, 'found' => isset($remote[$sku])], array_values($skus));

        if ($missing !== [] && ! $create) {
            return $this->result(self::STATUS_NOT_FOUND, 'No eBay inventory item has the SKU '.implode(', ', $missing)
                .'. Only listings created through the Inventory API are visible; check the SKU, or use create_listing.', ['skus' => $found]);
        }

        if ($missing !== []) {
            // create_listing with some SKUs already on eBay would overwrite listings we did not make.
            if ($remote !== [] || $catalogs->count() > 1) {
                return $this->result(self::STATUS_BLOCKED, 'Listings are created for a product with one catalog entry whose SKU is not on eBay yet; '
                    .'this product has '.($remote !== [] ? 'SKUs that already exist on eBay' : 'several catalog entries').'.', ['skus' => $found]);
            }

            return $this->createListing($catalogs->first(), $skus[$catalogs->first()->id], $o, $dryRun, $found);
        }

        // --- all SKUs exist: link ---------------------------------------------------------------
        return $this->linkExisting($catalogs, $skus, $remote, $dryRun, $found);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProductCatalogs>  $catalogs
     * @param  array<int, string>  $skus  catalog id => sku
     * @param  array<string, array{item: array<string, mixed>, offers: array<int, array<string, mixed>>}>  $remote
     * @param  array<int, array<string, mixed>>  $found
     * @return array<string, mixed>
     */
    private function linkExisting($catalogs, array $skus, array $remote, bool $dryRun, array $found): array
    {
        $adapter = $this->service->getAdapter();
        $applier = $this->service->applier();

        // One local product maps to one eBay product: a variation group, or a single item.
        $groupKeys = array_values(array_unique(array_map(fn ($r) => $r['item']['groupIds'][0] ?? null, $remote)));

        if ($catalogs->count() > 1 && (count($groupKeys) !== 1 || $groupKeys[0] === null)) {
            return $this->result(self::STATUS_BLOCKED, 'The SKUs of this product are not all in one eBay variation group, so they cannot map to one product.', ['skus' => $found]);
        }

        $group = $groupKeys[0] !== null ? $adapter->fetchGroup((string) $groupKeys[0]) : null;
        $first = array_key_first($remote);
        $externalProductId = $groupKeys[0] !== null ? (string) $groupKeys[0] : (string) $first;

        // A listing already mapped to another local product must not be claimed a second time.
        $takenProduct = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)->where('external_product_id', $externalProductId)->exists();
        $takenCatalog = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)->whereIn('external_catalog_id', array_values($skus))->pluck('external_catalog_id');

        if ($takenProduct || $takenCatalog->isNotEmpty()) {
            return $this->result(self::STATUS_BLOCKED, 'This eBay listing is already linked to another product ('
                .($takenCatalog->isNotEmpty() ? 'SKU '.$takenCatalog->implode(', ') : $externalProductId).').', ['skus' => $found]);
        }

        // What differs right now; the first sync will ask the user to settle each of these.
        $n = $adapter->normalizeProductData(['item' => $remote[$first]['item'], 'group' => $group, 'offers' => $remote[$first]['offers']]);
        $mismatches = $this->mismatches($n);

        $variants = [];
        foreach ($catalogs as $catalog) {
            $sku = $skus[$catalog->id];
            $variants[$sku] = $adapter->normalizeVariantData($remote[$sku]['item'], $remote[$sku]['offers'], $group);
            $mismatches = array_merge($mismatches, $this->variantMismatches($catalog, $variants[$sku]));
        }

        $skuInfo = [];
        foreach ($catalogs as $catalog) {
            $v = $variants[$skus[$catalog->id]];
            $skuInfo[] = ['sku' => $v['sku'], 'found' => true, 'offer_id' => $v['offer_id'], 'listing_id' => $v['listing_id'], 'offer_status' => $v['offer_status']];
        }

        if ($dryRun) {
            return $this->result(self::STATUS_DRY_RUN, 'Would link to eBay '.($group ? 'variation group ' : 'item ').$externalProductId.'.', ['skus' => $skuInfo, 'mismatches' => $mismatches]);
        }

        $listingId = collect($variants)->pluck('listing_id')->filter()->first();
        $applier->linkProduct($this->product, $externalProductId, $listingId);

        foreach ($catalogs as $catalog) {
            $applier->linkVariant($catalog, $variants[$skus[$catalog->id]]);
        }

        return $this->result(self::STATUS_LINKED, $mismatches === []
            ? 'Linked. Local data matches eBay.'
            : 'Linked. '.count($mismatches).' field(s) differ from eBay; the first sync will ask which side to keep.', [
                'skus' => $skuInfo,
                'mismatches' => $mismatches,
            ]);
    }

    /**
     * Create the eBay inventory item + offer from the local product, publish it and link it.
     *
     * @param  array<string, mixed>  $o
     * @param  array<int, array<string, mixed>>  $found
     * @return array<string, mixed>
     */
    private function createListing(ProductCatalogs $catalog, string $sku, array $o, bool $dryRun, array $found): array
    {
        $adapter = $this->service->getAdapter();
        $setup = $this->provider->getApiConfigArray()['ebay'] ?? [];
        $problems = [];

        $policies = $setup['policies'] ?? [];
        foreach (['fulfillment_policy_id' => 'fulfillment', 'payment_policy_id' => 'payment', 'return_policy_id' => 'return'] as $key => $label) {
            if (empty($policies[$key])) {
                $problems[] = "the {$label} policy is not chosen in the eBay setup";
            }
        }
        if (empty($setup['merchant_location_key'])) {
            $problems[] = 'no merchant location is chosen in the eBay setup';
        }

        $title = trim((string) $this->product->name);
        if ($title === '' || mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $problems[] = 'the product name must be 1-'.self::MAX_TITLE_LENGTH.' characters (eBay title limit)';
        }

        $price = (float) $catalog->price;
        if ($price <= 0) {
            $problems[] = 'the catalog price must be above zero';
        }

        $quantity = (int) $catalog->quantity_in_inventory;
        if ($quantity < 1) {
            $problems[] = 'the stock is 0, and eBay cannot publish a listing with no stock';
        }

        $marketCurrencyId = $this->service->getMarket()?->common_currency_id;
        $currency = $marketCurrencyId ? Currencies::withoutGlobalScopes()->where('id', $marketCurrencyId)->value('code') : null;
        if (! $currency) {
            $problems[] = 'the market has no currency';
        }

        $images = array_values(array_filter((array) ($o['image_urls'] ?? ($this->product->metadata['ebay']['images'] ?? []))));
        if ($images === []) {
            $problems[] = 'eBay needs at least one public image URL (pass image_urls)';
        }

        if ($problems !== []) {
            return $this->result(self::STATUS_BLOCKED, 'Cannot create the eBay listing: '.implode('; ', $problems).'.', ['skus' => $found]);
        }

        // Category and the aspects eBay requires for it. Both come from the Taxonomy API (application token).
        $taxonomy = new EbayClient($adapter->getClient()->getMarketplaceId());
        $treeId = (string) ($setup['category_tree_id'] ?? '');
        if ($treeId === '') {
            $treeId = (string) ($taxonomy->get('/commerce/taxonomy/v1/get_default_category_tree_id', ['marketplace_id' => $adapter->getClient()->getMarketplaceId()])['categoryTreeId'] ?? '');
        }

        $categoryId = (string) ($o['category_id'] ?? '');
        $suggestions = [];
        if ($categoryId === '') {
            $response = $taxonomy->get("/commerce/taxonomy/v1/category_tree/{$treeId}/get_category_suggestions", ['q' => $title]);
            foreach (array_slice((array) ($response['categorySuggestions'] ?? []), 0, 5) as $s) {
                $suggestions[] = ['category_id' => $s['category']['categoryId'] ?? null, 'name' => $s['category']['categoryName'] ?? null];
            }

            if ($suggestions === []) {
                return $this->result(self::STATUS_BLOCKED, 'eBay suggested no category for this title. Pass category_id.', ['skus' => $found]);
            }

            // The top suggestion is a guess about someone else's catalogue: only dry runs may use it.
            if (! $dryRun) {
                return $this->result(self::STATUS_BLOCKED, 'Pick the eBay category: pass category_id (run with dry_run to see suggestions).', ['skus' => $found, 'category_suggestions' => $suggestions]);
            }

            $categoryId = (string) $suggestions[0]['category_id'];
        }

        $aspectDefs = (array) ($taxonomy->get("/commerce/taxonomy/v1/category_tree/{$treeId}/get_item_aspects_for_category", ['category_id' => $categoryId])['aspects'] ?? []);
        $required = array_values(array_map(
            fn ($a) => $a['localizedAspectName'],
            array_filter($aspectDefs, fn ($a) => ! empty($a['aspectConstraint']['aspectRequired']))
        ));

        $aspects = (array) ($o['aspects'] ?? []);
        $missingAspects = array_values(array_filter($required, fn ($name) => empty($aspects[$name])));

        $itemBody = [
            'product' => array_filter([
                'title' => $title,
                'description' => trim((string) $this->product->description) ?: $title,
                'aspects' => $aspects ?: null,
                'imageUrls' => $images,
            ]),
            'condition' => (string) ($o['condition'] ?? $setup['default_condition'] ?? 'NEW'),
            'availability' => ['shipToLocationAvailability' => ['quantity' => $quantity]],
        ];

        $offerBody = [
            'sku' => $sku,
            'marketplaceId' => $adapter->getClient()->getMarketplaceId(),
            'format' => 'FIXED_PRICE',
            'availableQuantity' => $quantity,
            'categoryId' => $categoryId,
            'listingDescription' => $itemBody['product']['description'],
            'listingPolicies' => [
                'fulfillmentPolicyId' => $policies['fulfillment_policy_id'],
                'paymentPolicyId' => $policies['payment_policy_id'],
                'returnPolicyId' => $policies['return_policy_id'],
            ],
            'merchantLocationKey' => $setup['merchant_location_key'],
            'pricingSummary' => ['price' => ['value' => number_format($price, 2, '.', ''), 'currency' => $currency]],
        ];

        $plan = [
            'sku' => $sku,
            'category_id' => $categoryId,
            'category_suggestions' => $suggestions ?: null,
            'required_aspects' => $required,
            'inventory_item' => $itemBody,
            'offer' => $offerBody,
        ];

        if ($missingAspects !== []) {
            return $this->result(self::STATUS_BLOCKED, 'eBay requires these item aspects for this category: '.implode(', ', $missingAspects).'. Pass them as aspects {"Name": ["value"]}.', ['skus' => $found, 'plan' => $plan]);
        }

        if ($dryRun) {
            return $this->result(self::STATUS_DRY_RUN, 'Would create and publish an eBay listing for SKU '.$sku.' in category '.$categoryId.'. Nothing was sent.', ['skus' => $found, 'plan' => $plan]);
        }

        // --- writes to eBay, each step safe to repeat after a failure ---------------------------------
        $step = 'inventory item';
        try {
            $adapter->putInventoryItem($sku, $itemBody);

            $step = 'offer';
            $existingOffer = collect($adapter->fetchOffers($sku))->firstWhere('marketplaceId', $adapter->getClient()->getMarketplaceId());
            $offerId = $adapter->saveOffer($existingOffer['offerId'] ?? null, $offerBody);

            $step = 'publish';
            $listingId = $adapter->publishOffer($offerId);
        } catch (\Throwable $e) {
            throw new NotAllowedException("eBay refused the {$step} step: ".$e->getMessage().' Nothing was linked; repeating the call is safe.');
        }

        // --- link, then baseline with a normal sync so the first user sync is a no-op ---------------------
        $item = $adapter->fetchItem($sku) ?? $itemBody + ['sku' => $sku];
        $item['sku'] ??= $sku;
        $variant = $adapter->normalizeVariantData($item, $adapter->fetchOffers($sku));

        $applier = $this->service->applier();
        $applier->linkProduct($this->product, $sku, $listingId);
        $applier->linkVariant($catalog, $variant);

        EbayProductSyncService::forProduct($this->product, $this->provider->uuid)->sync(false, EbayProductSyncService::PREFER_LOCAL);

        return $this->result(self::STATUS_CREATED, 'Listing created and published on eBay.', [
            'skus' => [['sku' => $sku, 'found' => true, 'offer_id' => $offerId, 'listing_id' => $listingId, 'offer_status' => 'PUBLISHED']],
            'listing' => ['offer_id' => $offerId, 'listing_id' => $listingId, 'listing_url' => $this->listingUrl($listingId), 'category_id' => $categoryId],
        ]);
    }

    /**
     * @param  array<string, mixed>  $n  Normalised eBay product
     * @return array<int, array<string, mixed>>
     */
    private function mismatches(array $n): array
    {
        $out = [];

        foreach (['title' => [$this->product->name, $n['name']], 'description' => [$this->product->description, $n['description']]] as $field => [$local, $ebay]) {
            if (trim((string) $local) !== trim((string) $ebay)) {
                $out[] = ['field' => $field, 'local' => trim((string) $local), 'ebay' => trim((string) $ebay)];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $v  Normalised eBay variant
     * @return array<int, array<string, mixed>>
     */
    private function variantMismatches(ProductCatalogs $catalog, array $v): array
    {
        $out = [];

        if (abs((float) $catalog->price - (float) $v['price']) > 0.00001) {
            $out[] = ['field' => 'price', 'sku' => $v['sku'], 'local' => (float) $catalog->price, 'ebay' => (float) $v['price']];
        }
        if ((int) $catalog->quantity_in_inventory !== (int) $v['quantity_in_inventory']) {
            $out[] = ['field' => 'stock', 'sku' => $v['sku'], 'local' => (int) $catalog->quantity_in_inventory, 'ebay' => (int) $v['quantity_in_inventory']];
        }

        return $out;
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProductCatalogs>
     */
    private function catalogs()
    {
        return ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $this->product->id)
            // Only entries of the product's own account, like the apply service.
            ->where('iam_account_id', $this->product->iam_account_id)
            ->orderBy('id')
            ->get();
    }

    private function listingUrl(string $listingId): string
    {
        $host = strtolower((string) config('marketplace.ebay.environment', 'production')) === 'sandbox'
            ? 'www.sandbox.ebay.com'
            : 'www.ebay.com';

        return 'https://'.$host.'/itm/'.rawurlencode($listingId);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(string $status, string $message, array $extra = []): array
    {
        return ['status' => $status, 'message' => $message] + $extra;
    }
}
