<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;

/**
 * Added: links an EXISTING local product (and its catalog entries) to a product that already
 * exists on a Shopify shop, matching each catalog entry by SKU. The Shopify counterpart of
 * EbayLinkService, match mode only: it never creates anything on the shop.
 *
 * Shopify does not enforce SKU uniqueness, so a SKU is only a way to find the candidate, never
 * proof. Nothing is linked unless every catalog SKU resolves to exactly one variant, all of them
 * belong to one Shopify product, they cover every variant of that product (otherwise the next
 * pull would create duplicate local catalog entries for the rest), and none is already mapped.
 * Anything else is reported as blocked with the reason instead of guessed.
 *
 * Linking creates the product mapping and one catalog mapping per entry, baselined against the
 * shop's current state (see ShopifyApplyService::linkProduct), so it changes no data by itself.
 */
class ShopifyLinkService
{
    public const STATUS_LINKED = 'linked';

    public const STATUS_DRY_RUN = 'dry_run';

    public const STATUS_ALREADY = 'already_linked';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_BLOCKED = 'blocked';

    private ShopifyService $service;

    public function __construct(private readonly Products $product, private readonly Providers $provider)
    {
        $this->service = new ShopifyService($provider);
    }

    /**
     * Pick the Shopify provider to link against: same account as the product, active, connected,
     * optionally named.
     *
     * @throws NotFoundException When the account has no usable Shopify connection.
     * @throws NotAllowedException When several qualify and none was named.
     */
    public static function forProduct(Products $product, ?string $providerRef = null): self
    {
        // Added: each refusal names its own cause (see ExternalProviderResolver).
        return new self($product, ExternalProviderResolver::resolve($product, 'shopify', 'Shopify', $providerRef));
    }

    /**
     * @param  array<string, mixed>  $options  sku (only for a product with one catalog entry), dry_run
     * @return array<string, mixed> status = linked | already_linked | not_found | blocked | dry_run
     */
    public function link(array $options = []): array
    {
        $lock = Cache::lock('marketplace:shopify:link:'.$this->provider->id.':'.$this->product->id, 120);

        if (! $lock->get()) {
            throw new NotAllowedException('This product is already being linked.');
        }

        try {
            // The writes are the link's own bookkeeping, not an action the requesting user's roles cover.
            return UserHelper::withRolesCheckBypassed(fn () => $this->run($options));
        } catch (\RuntimeException $e) {
            // Added: a rejected token or a Shopify outage is a readable refusal, not a 500.
            throw new NotAllowedException(ExternalProviderResolver::describeFailure($e, 'Shopify'));
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

        $existing = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('marketplace_product_id', $this->product->id)
            ->exists();

        if ($existing) {
            return $this->result(self::STATUS_ALREADY, 'This product is already linked to Shopify.');
        }

        $catalogs = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $this->product->id)
            ->orderBy('id')
            ->get();

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

        if (count(array_unique(array_map('mb_strtolower', $skus))) !== count($skus)) {
            return $this->result(self::STATUS_BLOCKED, 'Two catalog entries share the same SKU, so they cannot be told apart on Shopify.');
        }

        // --- what Shopify has for them ---------------------------------------------------------
        $client = $this->service->getAdapter()->getClient();
        $hits = [];
        $found = [];
        $missing = [];

        foreach ($skus as $catalogId => $sku) {
            $matches = $this->variantsWithSku($sku);

            $found[] = ['sku' => $sku, 'matches' => count($matches)];

            if ($matches === []) {
                $missing[] = $sku;
            } elseif (count($matches) > 1) {
                return $this->result(self::STATUS_BLOCKED, 'SKU '.$sku.' is on '.count($matches).' Shopify variants, so it cannot identify one. '
                    .'Make it unique on Shopify first.', ['skus' => $found]);
            } else {
                $hits[$catalogId] = $matches[0];
            }
        }

        if ($missing !== []) {
            return $this->result(self::STATUS_NOT_FOUND, 'No Shopify variant has the SKU '.implode(', ', $missing).'. Check the SKU on both sides.', ['skus' => $found]);
        }

        $productIds = array_values(array_unique(array_map(fn ($h) => $h['product_id'], $hits)));

        if (count($productIds) !== 1) {
            return $this->result(self::STATUS_BLOCKED, 'The SKUs of this product are on different Shopify products, so they cannot map to one product.', ['skus' => $found]);
        }

        // --- the Shopify product itself, so the link matches it exactly -------------------------
        $node = data_get($client->query(ShopifyGraphQL::PRODUCT_BY_ID, ['id' => $productIds[0]]), 'product');

        if (! is_array($node)) {
            return $this->result(self::STATUS_NOT_FOUND, 'Shopify product '.$productIds[0].' could not be read.', ['skus' => $found]);
        }

        $n = $this->service->getAdapter()->normalizeProductData($node);
        $remoteById = collect($n['variants'])->keyBy('external_id');

        // A remote variant with no local entry would be created as a duplicate on the next pull.
        $unmatched = $remoteById->keys()->diff(array_column($hits, 'variant_id'));

        if ($unmatched->isNotEmpty()) {
            return $this->result(self::STATUS_BLOCKED, 'The Shopify product has '.$unmatched->count().' variant(s) this product has no catalog entry for: '
                .$unmatched->map(fn ($id) => $remoteById[$id]['sku'] ?: $remoteById[$id]['name'])->implode(', ')
                .'. Add them locally first, or the next sync would create duplicates.', ['skus' => $found]);
        }

        // A shop product or variant already mapped to another local product must not be claimed twice.
        $takenProduct = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)->where('external_product_id', $n['external_id'])->exists();
        $takenVariants = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->whereIn('external_catalog_id', array_column($hits, 'variant_id'))->exists();

        if ($takenProduct || $takenVariants) {
            return $this->result(self::STATUS_BLOCKED, 'This Shopify product is already linked to another product.', ['skus' => $found]);
        }

        // What differs right now, so the caller knows before the two sides start moving.
        $mismatches = [];

        if (trim((string) $this->product->name) !== trim((string) $n['name'])) {
            $mismatches[] = ['field' => 'name', 'local' => $this->product->name, 'shopify' => $n['name']];
        }

        foreach ($catalogs as $catalog) {
            $remote = $remoteById[$hits[$catalog->id]['variant_id']];

            if (abs((float) $catalog->price - (float) $remote['price']) >= 0.005) {
                $mismatches[] = ['field' => 'price', 'sku' => $skus[$catalog->id], 'local' => (float) $catalog->price, 'shopify' => (float) $remote['price']];
            }
        }

        $details = ['skus' => $found, 'shopify_product_id' => $n['external_id'], 'mismatches' => $mismatches];

        if ($dryRun) {
            return $this->result(self::STATUS_DRY_RUN, 'Would link to Shopify product '.$n['external_id'].'. Nothing was written.', $details);
        }

        $applier = $this->service->applier();
        $applier->linkProduct($this->product, $n);

        foreach ($catalogs as $catalog) {
            $applier->linkVariant($catalog, $remoteById[$hits[$catalog->id]['variant_id']]);
        }

        return $this->result(self::STATUS_LINKED, $mismatches === []
            ? 'Linked. Local data matches Shopify.'
            : 'Linked. '.count($mismatches).' field(s) differ from Shopify; nothing was changed on either side.', $details);
    }

    /**
     * Variants on the shop whose SKU equals this one exactly (the search itself is fuzzy).
     *
     * @return array<int, array{variant_id: string, product_id: string}>
     */
    private function variantsWithSku(string $sku): array
    {
        $term = 'sku:"'.addcslashes($sku, '"\\').'"';

        $edges = data_get(
            $this->service->getAdapter()->getClient()->query(ShopifyGraphQL::VARIANTS_BY_SKU, ['query' => $term]),
            'productVariants.edges',
            []
        );

        $out = [];

        foreach ($edges as $edge) {
            $node = $edge['node'] ?? [];

            if (mb_strtolower(trim((string) ($node['sku'] ?? ''))) === mb_strtolower($sku)) {
                $out[] = ['variant_id' => (string) $node['id'], 'product_id' => (string) data_get($node, 'product.id')];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(string $status, string $message, array $extra = []): array
    {
        return ['status' => $status, 'message' => $message, 'product_id' => $this->product->uuid] + $extra;
    }
}
