<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\MarketplaceAdapter;

/**
 * eBay seller adapter (Sell APIs, authenticated as the connected seller).
 *
 * Phase 1 shell: connection check and basic contract only. Product and inventory sync add
 * ProductSyncAdapter / InventorySyncAdapter when those methods are real — they are not declared
 * yet on purpose, because callers gate on `instanceof` and a stubbed capability would be
 * mistaken for a working one.
 *
 * Orders are out of scope for the eBay integration: the order methods are inert, like
 * IdeasoftAdapter::updateOrderStatus(), rather than missing.
 *
 * Not to be confused with EbayBrowseAdapter, which does keyword search with the platform keyset.
 */
class EbayAdapter implements MarketplaceAdapter
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
}
