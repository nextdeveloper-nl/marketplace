<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

/**
 * Keyword search over the public eBay catalogue (Browse API).
 *
 * Read-only and connection-less: it searches every listing on eBay with the
 * platform's application keyset, and stores nothing. It deliberately does not
 * implement ProductSyncAdapter — that contract synchronises a merchant's own
 * catalogue, which is the later seller-listings phase.
 *
 * normalizeItemSummary() is pure, like the Shopify normalizers, so it can be
 * tested against captured payloads without network access.
 */
class EbayBrowseAdapter
{
    /**
     * eBay's maximum page size for item_summary/search.
     */
    public const MAX_LIMIT = 200;

    /**
     * eBay refuses offset + limit beyond this.
     */
    public const MAX_OFFSET = 9999;

    public function __construct(private ?EbayClient $client = null)
    {
        $this->client ??= new EbayClient;
    }

    /**
     * Search listings by keyword.
     *
     * Supported options: limit, offset, filter (eBay filter syntax, e.g.
     * "price:[100..300],priceCurrency:USD,conditions:{NEW}"), sort ("price",
     * "-price", "newlyListed", "endingSoonest") and category_ids.
     *
     * @param  array<string, mixed>  $options
     * @return array{total: int, limit: int, offset: int, marketplace_id: string, items: array<int, array<string, mixed>>}
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '' && empty($options['category_ids'])) {
            throw new \InvalidArgumentException('An eBay search needs a query or a category.');
        }

        $limit = max(1, min(self::MAX_LIMIT, (int) ($options['limit'] ?? 50)));
        $offset = max(0, min(self::MAX_OFFSET, (int) ($options['offset'] ?? 0)));

        $params = array_filter([
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
            'filter' => $options['filter'] ?? null,
            'sort' => $options['sort'] ?? null,
            'category_ids' => $options['category_ids'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->client->get('/buy/browse/v1/item_summary/search', $params);

        return [
            'total' => (int) ($response['total'] ?? 0),
            'limit' => (int) ($response['limit'] ?? $limit),
            'offset' => (int) ($response['offset'] ?? $offset),
            'marketplace_id' => $this->client->getMarketplaceId(),
            'items' => array_map(
                fn (array $item) => $this->normalizeItemSummary($item),
                (array) ($response['itemSummaries'] ?? [])
            ),
        ];
    }

    /**
     * Map one Browse `itemSummary` into our shape.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeItemSummary(array $raw): array
    {
        $shipping = $raw['shippingOptions'][0]['shippingCost'] ?? null;

        return [
            'external_id' => $raw['itemId'] ?? null,
            'legacy_item_id' => $raw['legacyItemId'] ?? null,
            'title' => $raw['title'] ?? null,
            'price' => isset($raw['price']['value']) ? (float) $raw['price']['value'] : null,
            'currency' => $raw['price']['currency'] ?? null,
            'condition' => $raw['condition'] ?? null,
            'image_url' => $raw['image']['imageUrl'] ?? ($raw['thumbnailImages'][0]['imageUrl'] ?? null),
            'item_url' => $raw['itemWebUrl'] ?? null,
            'seller' => [
                'username' => $raw['seller']['username'] ?? null,
                'feedback_percentage' => isset($raw['seller']['feedbackPercentage'])
                    ? (float) $raw['seller']['feedbackPercentage']
                    : null,
                'feedback_score' => $raw['seller']['feedbackScore'] ?? null,
            ],
            'shipping_cost' => isset($shipping['value']) ? (float) $shipping['value'] : null,
            'shipping_currency' => $shipping['currency'] ?? null,
            'location' => [
                'country' => $raw['itemLocation']['country'] ?? null,
                'postal_code' => $raw['itemLocation']['postalCode'] ?? null,
            ],
            'buying_options' => (array) ($raw['buyingOptions'] ?? []),
            'categories' => array_values(array_filter(array_map(
                fn ($category) => $category['categoryId'] ?? null,
                (array) ($raw['categories'] ?? [])
            ))),
        ];
    }
}
