<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
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
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayUserException;

/**
 * Two-way sync of ONE already-linked product with eBay (the product page "Sync with eBay" action).
 *
 * "Which side is newer" cannot be read from eBay: inventory items and offers carry no modified
 * timestamp. So each side's change is detected against the last sync instead:
 *   - local changed  = the local record's updated_at is later than the mapping's last_synced_at
 *   - eBay changed   = the content hash of the eBay record differs from the hash stored at last sync
 * and the rule per record is: only local changed -> push; only eBay changed (or neither) -> pull;
 * both changed -> conflict, which is reported and left untouched unless the caller picks a side.
 *
 * Timestamps have one-second precision, so a local edit made within the same second as the end of a
 * sync is not seen as newer; the following edit is. Treating equal as newer would instead report a
 * spurious conflict after most syncs, which is the worse failure.
 *
 * Records and what they carry:
 *   product  title <-> name, description              (eBay inventory item, or its variation group)
 *   variant  price                                     (eBay offer)
 *   stock    quantity per SKU                          merged against args.ebay.synced_quantity, see below
 *
 * Stock is never a conflict: eBay has no compare-and-swap and sells units while we decide, so each
 * SKU is merged with the last agreed quantity M:  local L, eBay R ->
 *   R == L: nothing;  L == M: take R;  R == M: push L;  both moved: R + (L - M), floored at 0.
 *
 * After any push the eBay state is read again and applied locally, so the stored hashes match what
 * eBay now holds and the next run (or scheduled import) sees no difference. Only linked products
 * are handled here: creating a new eBay listing from a local product is a separate step.
 */
class EbayProductSyncService
{
    public const PREFER_LOCAL = 'local';

    public const PREFER_REMOTE = 'remote';

    /** eBay rejects inventory item titles longer than this. */
    private const MAX_TITLE_LENGTH = 80;

    private EbayService $service;

    public function __construct(private readonly Products $product, private readonly Providers $provider)
    {
        $this->service = new EbayService($provider);
    }

    /**
     * Find the eBay provider a product is linked to. A product linked through several providers
     * needs $providerRef (uuid) to say which one.
     *
     * @throws NotFoundException When the product is not linked to eBay.
     * @throws NotAllowedException When it is linked to several providers and none was named.
     */
    public static function forProduct(Products $product, ?string $providerRef = null): self
    {
        $providerIds = ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $product->id)
            ->pluck('marketplace_provider_id');

        $providers = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereIn('id', $providerIds)
            ->whereRaw("lower(trim(adapter)) = 'ebay'")
            // A provider of another account must never be driven from this product.
            ->where('iam_account_id', $product->iam_account_id)
            ->when($providerRef, fn ($q) => $q->where('uuid', $providerRef))
            ->get();

        if ($providers->isEmpty()) {
            throw new NotFoundException('This product is not linked to an eBay account. Import it from eBay first.');
        }

        if ($providers->count() > 1) {
            throw new NotAllowedException('This product is linked to several eBay accounts. Say which one with provider_id.');
        }

        $provider = $providers->first();

        if (! $provider->is_active) {
            throw new NotAllowedException('The eBay connection of this product is paused.');
        }

        return new self($product, $provider);
    }

    /**
     * Run the sync.
     *
     * @param  bool  $dryRun  Report what would happen, change nothing.
     * @param  string|null  $prefer  'local' or 'remote': settle conflicts in that side's favour.
     * @return array<string, mixed> status = synced | unchanged | conflict | dry_run, plus the per-field changes.
     */
    public function sync(bool $dryRun = false, ?string $prefer = null): array
    {
        if ($prefer !== null && ! in_array($prefer, [self::PREFER_LOCAL, self::PREFER_REMOTE], true)) {
            throw new NotAllowedException("prefer must be 'local' or 'remote'.");
        }

        // One sync per product at a time: two clicks must not interleave a push and a re-read.
        $lock = Cache::lock('marketplace:ebay:sync-product:'.$this->provider->id.':'.$this->product->id, 120);

        if (! $lock->get()) {
            throw new NotAllowedException('A sync of this product is already running.');
        }

        try {
            // The writes are the sync's own bookkeeping, not an action the requesting user's roles cover.
            return UserHelper::withRolesCheckBypassed(fn () => $this->run($dryRun, $prefer));
        } catch (EbayUserException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Added: a rejected token or an eBay outage is a readable refusal, not a 500.
            throw new NotAllowedException(ExternalProviderResolver::describeFailure($e, 'eBay'));
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function run(bool $dryRun, ?string $prefer): array
    {
        $adapter = $this->service->getAdapter();
        $applier = $this->service->applier();

        $productMapping = $this->productMapping();
        $links = $this->catalogLinks();

        if ($productMapping === null || $links === []) {
            throw new NotFoundException('This product is not linked to an eBay listing. Import it from eBay first.');
        }

        $remote = $this->readRemote($links);

        $changes = [];
        $conflicts = [];
        $itemEdits = [];      // sku => ['title' => .., 'description' => ..] to merge into the eBay item
        $groupEdit = null;    // ['title' => .., 'description' => ..] to merge into the eBay group
        $priceQty = [];       // sku => bulk update entry
        $pullProduct = false;
        $stock = [];          // catalog id => final quantity

        // --- product record: title + description ------------------------------------------------
        $n = $remote['product'];
        $localTitle = trim((string) $this->product->name);
        $localDescription = trim((string) $this->product->description);

        $titleDiffers = $localTitle !== trim((string) $n['name']);
        $descriptionDiffers = $localDescription !== trim((string) $n['description']);

        if ($titleDiffers || $descriptionDiffers) {
            $localChanged = $this->isLocalNewer($this->product->updated_at, $productMapping->last_synced_at);
            $remoteChanged = $productMapping->sync_hash !== $n['sync_hash'];
            $direction = $this->decide($localChanged, $remoteChanged, $prefer);

            foreach (['title' => [$titleDiffers, $localTitle, $n['name']], 'description' => [$descriptionDiffers, $localDescription, $n['description']]] as $field => [$differs, $local, $theirs]) {
                if (! $differs) {
                    continue;
                }

                $changes[] = ['record' => 'product', 'field' => $field, 'direction' => $direction, 'local' => $local, 'ebay' => $theirs];

                if ($direction === 'conflict') {
                    $conflicts[] = "product {$field}";
                }
            }

            if ($direction === 'push') {
                if ($titleDiffers && mb_strlen($localTitle) > self::MAX_TITLE_LENGTH) {
                    throw new NotAllowedException('The product name is longer than eBay allows ('.self::MAX_TITLE_LENGTH.' characters); shorten it before syncing.');
                }

                $edit = ['title' => $localTitle, 'description' => $localDescription];

                if ($remote['group'] !== null) {
                    $groupEdit = $edit;
                } else {
                    $itemEdits[array_key_first($links)] = $edit;
                }
            } elseif ($direction === 'pull') {
                $pullProduct = true;
            }
        }

        // --- variants: price, and stock per SKU --------------------------------------------------
        foreach ($links as $sku => $link) {
            /** @var ProductCatalogs $catalog */
            $catalog = $link['catalog'];
            /** @var ProductCatalogMappings $mapping */
            $mapping = $link['mapping'];
            $v = $remote['variants'][$sku];

            if (abs((float) $catalog->price - (float) $v['price']) > 0.00001) {
                $localChanged = $this->isLocalNewer($catalog->updated_at, $mapping->last_synced_at);
                $remoteChanged = $mapping->sync_hash !== $v['sync_hash'];
                $direction = $this->decide($localChanged, $remoteChanged, $prefer);

                $changes[] = ['record' => 'variant', 'sku' => $sku, 'field' => 'price', 'direction' => $direction, 'local' => (float) $catalog->price, 'ebay' => (float) $v['price']];

                if ($direction === 'conflict') {
                    $conflicts[] = "{$sku} price";
                } elseif ($direction === 'push') {
                    $this->assertCanPushPrice($v);
                    $priceQty[$sku] ??= ['sku' => $sku, 'quantity' => null, 'offer_id' => $v['offer_id'], 'price' => null, 'currency' => $v['currency']];
                    $priceQty[$sku]['price'] = (float) $catalog->price;
                }
            }

            $final = $this->mergeStock((int) $catalog->quantity_in_inventory, (int) $v['quantity_in_inventory'], $catalog->args['ebay']['synced_quantity'] ?? null);

            // eBay holds a different number than the merged result: write it there.
            if ($final !== (int) $v['quantity_in_inventory']) {
                $priceQty[$sku] ??= ['sku' => $sku, 'quantity' => null, 'offer_id' => $v['offer_id'], 'price' => null, 'currency' => $v['currency']];
                $priceQty[$sku]['quantity'] = $final;
            }

            if ($final !== (int) $catalog->quantity_in_inventory || $final !== (int) $v['quantity_in_inventory']) {
                $changes[] = [
                    'record' => 'variant', 'sku' => $sku, 'field' => 'stock',
                    'direction' => $final === (int) $v['quantity_in_inventory'] ? 'pull' : 'push',
                    'local' => (int) $catalog->quantity_in_inventory, 'ebay' => (int) $v['quantity_in_inventory'], 'result' => $final,
                ];
            }

            $stock[$catalog->id] = $final;
        }

        if ($conflicts !== []) {
            return ['status' => 'conflict', 'conflicts' => $conflicts, 'changes' => $changes,
                'hint' => "Both sides changed since the last sync. Repeat with prefer='local' or prefer='remote'."];
        }

        if ($dryRun) {
            return ['status' => 'dry_run', 'changes' => $changes];
        }

        // --- writes to eBay ----------------------------------------------------------------------
        foreach ($itemEdits as $sku => $edit) {
            $item = $remote['items'][$sku];
            $item['product']['title'] = $edit['title'];
            $item['product']['description'] = $edit['description'];
            $adapter->putInventoryItem($sku, $item);
        }

        if ($groupEdit !== null) {
            $group = $remote['group'];
            $group['title'] = $groupEdit['title'];
            $group['description'] = $groupEdit['description'];
            $adapter->putGroup((string) $group['inventoryItemGroupKey'], $group);
        }

        if ($priceQty !== []) {
            $adapter->bulkUpdatePriceQuantity(array_values($priceQty));
        }

        // --- bring the local side to the state both sides now agree on -----------------------------
        // Re-read eBay so the stored hashes describe what eBay really holds after our writes.
        $fresh = $priceQty !== [] || $itemEdits !== [] || $groupEdit !== null ? $this->readRemote($links) : $remote;

        // Product-level fields are applied only when the decision was to pull (or nothing differed);
        // a pending local-only edit has just been pushed, so the fresh eBay state equals it.
        $applier->applyProduct($fresh['product']);

        foreach ($links as $sku => $link) {
            $applier->applyVariant($fresh['product']['external_id'], $fresh['variants'][$sku], $fresh['product']['name']);

            $catalog = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($link['catalog']->id);
            $applier->setQuantity($catalog, $stock[$catalog->id]);

            // applyVariant skips unchanged records; make sure the sync is stamped either way.
            $mapping = $link['mapping']->fresh();
            $mapping->last_synced_at = Carbon::now();
            $mapping->saveQuietly();
        }

        $productMapping = $productMapping->fresh();
        $productMapping->last_synced_at = Carbon::now();
        $productMapping->saveQuietly();

        return [
            'status' => $changes === [] ? 'unchanged' : 'synced',
            'changes' => $changes,
        ];
    }

    /**
     * Local vs eBay for a record where they differ: which way does it go?
     */
    private function decide(bool $localChanged, bool $remoteChanged, ?string $prefer): string
    {
        if ($localChanged && $remoteChanged) {
            return match ($prefer) {
                self::PREFER_LOCAL => 'push',
                self::PREFER_REMOTE => 'pull',
                default => 'conflict',
            };
        }

        // Only the local side moved: push. Only eBay moved, or the difference is unexplained: eBay wins.
        return $localChanged ? 'push' : 'pull';
    }

    /**
     * Stock merge against the last agreed quantity (see the class comment). $agreed is null for a
     * catalog entry that has never been synced with the marker: eBay is taken as the truth.
     */
    private function mergeStock(int $local, int $ebay, mixed $agreed): int
    {
        if ($local === $ebay) {
            return $ebay;
        }

        if ($agreed === null) {
            return $ebay;
        }

        $agreed = (int) $agreed;

        if ($local === $agreed) {
            return $ebay;
        }

        if ($ebay === $agreed) {
            return $local;
        }

        // Both moved (typically a sale on eBay while we edited locally): keep the eBay sales, apply our delta.
        return max(0, $ebay + ($local - $agreed));
    }

    private function isLocalNewer(mixed $updatedAt, mixed $lastSyncedAt): bool
    {
        if (! $updatedAt) {
            return false;
        }

        // Never synced -> any local edit counts as newer.
        if (! $lastSyncedAt) {
            return true;
        }

        return Carbon::parse($updatedAt)->greaterThan(Carbon::parse($lastSyncedAt));
    }

    /**
     * Refuse a price push eBay would take in the wrong currency, or that has no offer to carry it.
     *
     * @param  array<string, mixed>  $variant
     */
    private function assertCanPushPrice(array $variant): void
    {
        if (empty($variant['offer_id'])) {
            throw new NotAllowedException('SKU '.$variant['sku'].' has no eBay offer yet, so its price cannot be pushed.');
        }

        $marketCurrencyId = $this->service->getMarket()?->common_currency_id;
        $code = $marketCurrencyId
            ? Currencies::withoutGlobalScopes()->where('id', $marketCurrencyId)->value('code')
            : null;

        if (! $code || strtoupper((string) $code) !== strtoupper((string) $variant['currency'])) {
            throw new NotAllowedException(sprintf(
                'The market currency (%s) differs from the eBay offer currency (%s); the price is not pushed to avoid a wrong amount.',
                $code ?: 'unknown',
                $variant['currency'] ?: 'unknown'
            ));
        }
    }

    private function productMapping(): ?ProductMappings
    {
        return ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('marketplace_product_id', $this->product->id)
            ->first();
    }

    /**
     * The product's SKUs on this provider, keyed by SKU, each with its catalog entry and mapping.
     *
     * @return array<string, array{catalog: ProductCatalogs, mapping: ProductCatalogMappings}>
     */
    private function catalogLinks(): array
    {
        $catalogs = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $this->product->id)
            // Only entries of the product's own account, like the apply service.
            ->where('iam_account_id', $this->product->iam_account_id)
            ->get()->keyBy('id');

        $links = [];

        $mappings = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->whereIn('marketplace_product_catalog_id', $catalogs->keys())
            ->get();

        foreach ($mappings as $mapping) {
            $links[(string) $mapping->external_catalog_id] = [
                'catalog' => $catalogs[$mapping->marketplace_product_catalog_id],
                'mapping' => $mapping,
            ];
        }

        return $links;
    }

    /**
     * Read the current eBay state of every linked SKU and normalise it.
     *
     * @param  array<string, array{catalog: ProductCatalogs, mapping: ProductCatalogMappings}>  $links
     * @return array{product: array<string, mixed>, variants: array<string, array<string, mixed>>, items: array<string, array<string, mixed>>, group: array<string, mixed>|null}
     */
    private function readRemote(array $links): array
    {
        $adapter = $this->service->getAdapter();

        $items = [];
        $variants = [];
        $group = null;
        $first = null;

        foreach (array_keys($links) as $sku) {
            $item = $adapter->fetchItem($sku);

            if ($item === null) {
                throw new NotFoundException("SKU {$sku} no longer exists on eBay.");
            }

            $item['sku'] ??= $sku;
            $offers = $adapter->fetchOffers($sku);

            if ($group === null && ! empty($item['groupIds'][0])) {
                $group = $adapter->fetchGroup((string) $item['groupIds'][0]);
            }

            $items[$sku] = $item;
            $variants[$sku] = ['item' => $item, 'offers' => $offers];
            $first ??= $sku;
        }

        foreach ($variants as $sku => $parts) {
            $variants[$sku] = $adapter->normalizeVariantData($parts['item'], $parts['offers'], $group);
        }

        $product = $adapter->normalizeProductData([
            'item' => $items[$first],
            'group' => $group,
            'offers' => $adapter->fetchOffers($first),
        ]);

        return ['product' => $product, 'variants' => $variants, 'items' => $items, 'group' => $group];
    }
}
