<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\InventorySyncAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\MarketplaceAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\ProductSyncAdapter;

/**
 * eBay seller adapter (Sell Inventory API, authenticated as the connected seller).
 *
 * Phase 2: pull only. It reads the seller's Inventory API catalogue:
 *   inventory items (SKU, title, images, stock)  +  offers (price, listing id, status)
 *   +  inventory item groups (variation parents).
 * Listings created outside the Inventory API (Seller Hub, Trading API) are not visible to
 * these endpoints; migrating them is out of scope.
 *
 * eBay has no "modified since" filter, so there is no delta: a scan walks the whole inventory in
 * pages and the apply layer skips unchanged records through a content hash. $since arguments of
 * the shared interfaces are therefore accepted and ignored.
 *
 * Push methods exist only to satisfy the shared interfaces and refuse to run until the push
 * phase is built, like ShopifyAdapter::pushProduct() before its push gate was enabled.
 *
 * Orders are out of scope: the order methods are inert, like IdeasoftAdapter::updateOrderStatus().
 *
 * Not to be confused with EbayBrowseAdapter, which does keyword search with the platform keyset.
 */
class EbayAdapter implements InventorySyncAdapter, MarketplaceAdapter, ProductSyncAdapter
{
    private Providers $provider;

    private EbayClient $client;

    public function __construct(Providers $provider)
    {
        $this->provider = $provider;
        $this->client = new EbayClient(null, $provider);
    }

    public function getClient(): EbayClient
    {
        return $this->client;
    }

    // -------------------------------------------------------------------------
    // MarketplaceAdapter
    // -------------------------------------------------------------------------

    /**
     * Live check: asks eBay who the stored token belongs to. True only when eBay answers with a
     * user, so a revoked or expired-and-unrefreshable connection reports false.
     *
     * @param  array<string, mixed>  $credentials  Unused; credentials come from the provider row.
     */
    public function authenticate(array $credentials = []): bool
    {
        try {
            return $this->identity() !== [];
        } catch (\Throwable $e) {
            Log::warning(__METHOD__.' - eBay authentication check failed', [
                'provider_id' => $this->provider->id,
                'exception' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * The connected seller's identity (userId, username, ...), or [] when eBay returned none.
     *
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        $user = $this->client->get('/commerce/identity/v1/user');

        return empty($user['userId']) && empty($user['username']) ? [] : $user;
    }

    /**
     * Orders are not part of the eBay integration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrders(Carbon|\Carbon\Carbon $since): array
    {
        return [];
    }

    /**
     * Orders are not part of the eBay integration; kept as a logged no-op to satisfy the base
     * contract explicitly rather than by omission.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        Log::info(__METHOD__.' - order status push-back is not supported for eBay', [
            'provider_id' => $this->provider->id,
            'external_order_id' => $orderId,
            'requested_status' => $status,
        ]);

        return false;
    }

    /**
     * @param  array<string, mixed>  $rawOrder
     * @return array<string, mixed>
     */
    public function normalizeOrderData(array $rawOrder): array
    {
        return [];
    }

    /**
     * eBay change notifications (Commerce Notification API) arrive in a later phase.
     *
     * @return array<string, mixed>
     */
    public function getWebhookConfig(): array
    {
        return [
            'supported' => false,
            'supported_events' => [],
            'note' => 'eBay Commerce Notifications are not wired yet; sync relies on polling.',
        ];
    }

    // -------------------------------------------------------------------------
    // Inventory API reads
    // -------------------------------------------------------------------------

    /**
     * One page of inventory items.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, next_offset: int, has_more: bool}
     */
    public function fetchInventoryPage(int $offset = 0, ?int $limit = null): array
    {
        $limit ??= $this->pageSize();

        try {
            $response = $this->client->get('/sell/inventory/v1/inventory_item', [
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (EbayUserException $e) {
            // A seller with no inventory items at all is answered with a 404 on some marketplaces.
            if ($e->getStatus() === 404) {
                return ['items' => [], 'total' => 0, 'next_offset' => $offset, 'has_more' => false];
            }

            throw $e;
        }

        $items = (array) ($response['inventoryItems'] ?? []);
        $total = (int) ($response['total'] ?? count($items));
        $next = $offset + count($items);

        return [
            'items' => $items,
            // Guard against a short/empty page looping forever.
            'next_offset' => $next,
            'total' => $total,
            'has_more' => $items !== [] && $next < $total,
        ];
    }

    /**
     * The offers eBay holds for one SKU (one per marketplace). [] when the SKU has none yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOffers(string $sku): array
    {
        try {
            $response = $this->client->get('/sell/inventory/v1/offer', ['sku' => $sku]);
        } catch (EbayUserException $e) {
            // eBay answers 404 for a SKU that has an item but no offer.
            if ($e->getStatus() === 404) {
                return [];
            }

            throw $e;
        }

        return (array) ($response['offers'] ?? []);
    }

    /**
     * A variation group (the parent of several SKUs), or null when eBay no longer has it.
     *
     * @return array<string, mixed>|null
     */
    public function fetchGroup(string $groupKey): ?array
    {
        try {
            return $this->client->get('/sell/inventory/v1/inventory_item_group/'.rawurlencode($groupKey));
        } catch (EbayUserException $e) {
            if ($e->getStatus() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Walk the whole inventory, yielding one raw record per SKU: the item, its variation group (if
     * any) and its offers. Streams page by page so a big catalogue never sits in memory.
     *
     * @param  int  $startOffset  Resume position (the first run starts at 0).
     * @param  callable(int|null): void|null  $onPageDone  Called after each page is fully yielded with
     *                                                     the offset of the next page, or null after the last.
     * @return \Generator<int, array{item: array<string, mixed>, group: array<string, mixed>|null, offers: array<int, array<string, mixed>>}>
     */
    public function scanProducts(int $startOffset = 0, ?callable $onPageDone = null): \Generator
    {
        $offset = $startOffset;
        $groups = [];

        do {
            $page = $this->fetchInventoryPage($offset);

            foreach ($page['items'] as $item) {
                $sku = (string) ($item['sku'] ?? '');

                if ($sku === '') {
                    continue;
                }

                // Variation groups are shared by many SKUs: fetch each once per scan.
                $group = null;
                $groupKey = $item['groupIds'][0] ?? null;
                if ($groupKey) {
                    $groups[$groupKey] ??= $this->fetchGroup((string) $groupKey) ?? ['inventoryItemGroupKey' => $groupKey];
                    $group = $groups[$groupKey];
                }

                yield ['item' => $item, 'group' => $group, 'offers' => $this->fetchOffers($sku)];
            }

            $offset = $page['next_offset'];

            if ($onPageDone) {
                $onPageDone($page['has_more'] ? $offset : null);
            }
        } while ($page['has_more']);
    }

    // -------------------------------------------------------------------------
    // ProductSyncAdapter
    // -------------------------------------------------------------------------

    /**
     * Every inventory record. eBay has no modified-since filter, so $since is ignored; prefer
     * scanProducts() for big catalogues because this loads them all at once.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(Carbon $since): array
    {
        return iterator_to_array($this->scanProducts(), false);
    }

    /**
     * Normalise the PRODUCT level of a raw scan record: the variation group when the SKU belongs
     * to one, otherwise the SKU itself (a single-variant product). The variant is normalised
     * separately by normalizeVariantData(), because group products are shared by several SKUs.
     *
     * @param  array{item: array<string, mixed>, group?: array<string, mixed>|null, offers?: array<int, array<string, mixed>>}  $rawProduct
     * @return array<string, mixed>
     */
    public function normalizeProductData(array $rawProduct): array
    {
        $item = $rawProduct['item'];
        $group = $rawProduct['group'] ?? null;
        $offer = $this->pickOffer((array) ($rawProduct['offers'] ?? []));

        if ($group !== null) {
            $externalId = (string) ($group['inventoryItemGroupKey'] ?? $item['groupIds'][0]);
            $name = (string) ($group['title'] ?? $externalId);
            $description = (string) ($group['description'] ?? '');
            $images = array_values((array) ($group['imageUrls'] ?? []));
            // Whether the group is live is carried by its variants' offers.
            $isActive = true;
            $categories = [];
            // Currency and condition belong to a SKU/offer, not to the group. Taking them from
            // whichever SKU happens to be processed would flip the group's hash between its
            // siblings and rewrite the product on every run.
            $currency = null;
            $condition = null;
        } else {
            $externalId = (string) $item['sku'];
            $name = (string) ($item['product']['title'] ?? $externalId);
            $description = (string) ($item['product']['description'] ?? '');
            $images = array_values((array) ($item['product']['imageUrls'] ?? []));
            $isActive = ($offer['status'] ?? null) === 'PUBLISHED';
            $categories = isset($offer['categoryId']) ? [(string) $offer['categoryId']] : [];
            $currency = $offer['pricingSummary']['price']['currency'] ?? null;
            $condition = $item['condition'] ?? null;
        }

        $n = [
            'external_id' => $externalId,
            'kind' => $group !== null ? 'group' : 'item',
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
            'categories' => $categories,
            'images' => $images,
            'currency' => $currency,
            'condition' => $condition,
            // eBay exposes no modification time for inventory records; the ordering guard
            // therefore has nothing to compare and the content hash carries the weight.
            'external_updated_at' => null,
            'variants' => [],
        ];

        $n['sync_hash'] = $this->hash(array_diff_key($n, ['external_updated_at' => 1, 'variants' => 1]));

        return $n;
    }

    /**
     * Normalise one SKU into a local catalog (variant) record.
     *
     * @param  array<string, mixed>  $item  Raw inventory item
     * @param  array<int, array<string, mixed>>  $offers  Raw offers for that SKU
     * @param  array<string, mixed>|null  $group  Raw variation group, when the SKU belongs to one
     * @return array<string, mixed>
     */
    public function normalizeVariantData(array $item, array $offers, ?array $group = null): array
    {
        $offer = $this->pickOffer($offers);
        $sku = (string) $item['sku'];

        // A group names its variants by the aspects they vary by (Size, Color, ...).
        $variesBy = array_map(
            fn ($spec) => $spec['name'] ?? null,
            (array) ($group['variesBy']['specifications'] ?? [])
        );
        $aspects = (array) ($item['product']['aspects'] ?? []);
        $nameParts = [];
        foreach (array_filter($variesBy) as $aspectName) {
            if (! empty($aspects[$aspectName][0])) {
                $nameParts[] = $aspects[$aspectName][0];
            }
        }

        $variant = [
            'external_id' => $sku,
            'sku' => $sku,
            'name' => implode(' / ', $nameParts),
            'price' => (float) ($offer['pricingSummary']['price']['value'] ?? 0),
            'currency' => $offer['pricingSummary']['price']['currency'] ?? null,
            'barcode' => $item['product']['ean'][0] ?? $item['product']['upc'][0] ?? null,
            'quantity_in_inventory' => (int) ($item['availability']['shipToLocationAvailability']['quantity'] ?? 0),
            'offer_id' => $offer['offerId'] ?? null,
            'listing_id' => $offer['listing']['listingId'] ?? null,
            'offer_status' => $offer['status'] ?? null,
            'external_updated_at' => null,
        ];

        // Stock is left out of the hash: the inventory job owns quantities, so a stock-only change
        // must not rewrite the catalogue.
        $variant['sync_hash'] = $this->hash(array_diff_key($variant, ['quantity_in_inventory' => 1, 'external_updated_at' => 1]));

        return $variant;
    }

    /**
     * Listing-level push is not built yet.
     *
     * @return array<string, mixed>
     */
    public function pushProduct(Products $product): array
    {
        throw new NotAllowedException('Pushing products to eBay is not enabled yet.');
    }

    /**
     * Listing-level push is not built yet.
     *
     * @return array<string, mixed>
     */
    public function pushCatalog(ProductCatalogs $catalog): array
    {
        throw new NotAllowedException('Pushing catalog entries to eBay is not enabled yet.');
    }

    // -------------------------------------------------------------------------
    // InventorySyncAdapter
    // -------------------------------------------------------------------------

    /**
     * Stock levels for every SKU. No delta on eBay: $since is ignored.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchInventoryLevels(Carbon $since): array
    {
        $levels = [];
        $offset = 0;

        do {
            $page = $this->fetchInventoryPage($offset);

            foreach ($page['items'] as $item) {
                $levels[] = $this->inventoryLevel($item);
            }

            $offset = $page['next_offset'];
        } while ($page['has_more']);

        return $levels;
    }

    /**
     * The stock level of one raw inventory item, in the shape the apply layer expects.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function inventoryLevel(array $item): array
    {
        return [
            'external_variant_id' => (string) $item['sku'],
            'sku' => (string) $item['sku'],
            'available' => (int) ($item['availability']['shipToLocationAvailability']['quantity'] ?? 0),
            'external_updated_at' => null,
        ];
    }

    /**
     * Stock push is not built yet.
     */
    public function pushInventoryLevel(ProductCatalogs $catalog, int $quantity, ?int $changeFrom = null, ?string $operationRef = null): bool
    {
        throw new NotAllowedException('Pushing inventory to eBay is not enabled yet.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * The offer for this provider's eBay site, falling back to the first one.
     *
     * @param  array<int, array<string, mixed>>  $offers
     * @return array<string, mixed>
     */
    private function pickOffer(array $offers): array
    {
        foreach ($offers as $offer) {
            if (($offer['marketplaceId'] ?? null) === $this->client->getMarketplaceId()) {
                return $offer;
            }
        }

        return $offers[0] ?? [];
    }

    /**
     * Deterministic fingerprint of the stable fields of a normalised record.
     *
     * @param  array<string, mixed>  $fields
     */
    private function hash(array $fields): string
    {
        // Sorted keys so field order never changes the hash.
        ksort($fields);

        return hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function pageSize(): int
    {
        // eBay caps getInventoryItems at 200 per page.
        return max(1, min(200, (int) config('marketplace.ebay.scan_page_size', 100)));
    }
}
