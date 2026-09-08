<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Database\Models\WebhookEvents;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyApplyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Drains the webhook inbox.
 *
 * The controller only verifies the signature and stores the delivery, because
 * Shopify allows five seconds and deletes the subscription after eight
 * consecutive failures. Interpretation happens here.
 *
 * Two things shape the design:
 *
 * 1. **Deliveries are neither ordered nor guaranteed.** Events are drained
 *    oldest-triggered-first, and rather than trusting the delivered body the
 *    processor re-fetches the resource from the Admin API. A webhook is a
 *    signal that something changed, not a reliable snapshot of what it changed
 *    to — and the payloads are REST-shaped while the normalizers speak GraphQL,
 *    so re-fetching removes a whole translation layer as well.
 * 2. **Applying must be identical to the scheduled pull.** Both go through
 *    ShopifyApplyService, so the fingerprint, ordering and quiet-write rules
 *    cannot drift apart between the two paths.
 *
 * An event that fails is left unprocessed with its attempt count raised until
 * MAX_ATTEMPTS, after which it stops being retried and stays in the table as a
 * dead letter for the operator to inspect and replay.
 */
class ProcessShopifyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Attempts before an event is parked as a dead letter.
     */
    public const MAX_ATTEMPTS = 5;

    public int $timeout = 600;

    public int $tries = 1;

    /** @var array<string, mixed> */
    public array $report = [];

    public function __construct(public int $batchSize = 200, public ?int $providerId = null)
    {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(): void
    {
        $events = WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('is_processed', false)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->when($this->providerId, fn ($q) => $q->where('marketplace_provider_id', $this->providerId))
            // Oldest trigger first. Shopify does not order deliveries, so this
            // is the only chance to replay a burst in the order it happened.
            ->orderByRaw('triggered_at asc nulls first')
            ->orderBy('id')
            ->limit($this->batchSize)
            ->get();

        $handled = $failed = $ignored = 0;
        $byTopic = [];

        /** @var array<int, ShopifyService|null> $services */
        $services = [];

        foreach ($events as $event) {
            $topic = strtolower((string) $event->topic);
            $byTopic[$topic] = ($byTopic[$topic] ?? 0) + 1;

            try {
                $service = $services[$event->marketplace_provider_id]
                    ??= $this->serviceFor((int) $event->marketplace_provider_id);

                if (! $service) {
                    $this->markProcessed($event, 'provider missing or not a Shopify connection');
                    $ignored++;

                    continue;
                }

                $outcome = $this->route($service, $event, $topic);

                $this->markProcessed($event, $outcome);
                $outcome === 'ignored' ? $ignored++ : $handled++;
            } catch (\Throwable $e) {
                $failed++;
                $this->markFailed($event, $e);
            }
        }

        $this->report = [
            'events' => $events->count(),
            'handled' => $handled,
            'ignored' => $ignored,
            'failed' => $failed,
            'topics' => $byTopic,
        ];

        if ($events->isNotEmpty()) {
            Log::info(static::class.' - webhook batch processed', $this->report);
        }
    }

    /**
     * Dispatch one event to the code that knows what the topic means.
     */
    private function route(ShopifyService $service, WebhookEvents $event, string $topic): string
    {
        $payload = is_array($event->payload) ? $event->payload : [];
        $applier = $service->applier();

        return match (true) {
            $topic === 'products/create', $topic === 'products/update' => $this->refetchProduct($service, $payload),
            $topic === 'products/delete' => $applier->deleteProduct($this->gid($payload, 'Product')),

            str_starts_with($topic, 'inventory_levels/') => $this->refetchInventoryLevel($service, $payload, $topic),

            $topic === 'orders/delete' => $applier->cancelOrder($this->gid($payload, 'Order')),
            str_starts_with($topic, 'orders/'), str_starts_with($topic, 'fulfillments/'), $topic === 'refunds/create' => $this->refetchOrder($service, $payload, $topic),

            $topic === 'customers/delete' => $applier->deleteCustomerMapping($this->gid($payload, 'Customer')),
            $topic === 'customers/create', $topic === 'customers/update' => $this->refetchCustomer($service, $payload),

            $topic === 'app/uninstalled' => $this->appUninstalled($service),
            $topic === 'app/scopes_update' => $this->scopesUpdated($service, $payload),

            $topic === 'customers/data_request' => $this->complianceDataRequest($service, $event, $payload),
            $topic === 'customers/redact' => $this->complianceCustomerRedact($service, $payload),
            $topic === 'shop/redact' => $this->complianceShopRedact($service),

            default => 'ignored',
        };
    }

    // -------------------------------------------------------------------------
    // Re-fetch handlers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refetchProduct(ShopifyService $service, array $payload): string
    {
        $gid = $this->gid($payload, 'Product');
        $data = $service->getAdapter()->getClient()->query(ShopifyGraphQL::PRODUCT_BY_ID, ['id' => $gid]);
        $node = data_get($data, 'product');

        // Deleted between the webhook firing and us reading it. Not an error:
        // the delete webhook, or the nightly sweep, will remove it.
        if (! is_array($node)) {
            return 'gone';
        }

        return $service->applier()->applyProduct($service->getAdapter()->normalizeProductData($node));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refetchOrder(ShopifyService $service, array $payload, string $topic): string
    {
        // fulfillments/* and refunds/create describe a child object; the id we
        // care about is the order it belongs to.
        $orderId = $payload['order_id'] ?? $payload['id'] ?? null;
        $gid = $this->gidFrom($payload['admin_graphql_api_order_id'] ?? null, $orderId, 'Order');

        if ($gid === '') {
            return 'ignored';
        }

        $data = $service->getAdapter()->getClient()->query(ShopifyGraphQL::ORDER_BY_ID, ['id' => $gid]);
        $node = data_get($data, 'order');

        if (! is_array($node)) {
            return 'gone';
        }

        return $service->applier()->applyOrder($service->getAdapter()->normalizeOrderData($node));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refetchCustomer(ShopifyService $service, array $payload): string
    {
        $gid = $this->gid($payload, 'Customer');
        $data = $service->getAdapter()->getClient()->query(ShopifyGraphQL::CUSTOMER_BY_ID, ['id' => $gid]);
        $node = data_get($data, 'customer');

        if (! is_array($node)) {
            return 'gone';
        }

        $mapping = $service->applier()->applyCustomer($service->getAdapter()->normalizeCustomerData($node));

        return $mapping ? ShopifyApplyService::APPLIED : 'ignored';
    }

    /**
     * Inventory webhooks fire for every location; only the pinned one matters.
     *
     * @param  array<string, mixed>  $payload
     */
    private function refetchInventoryLevel(ShopifyService $service, array $payload, string $topic): string
    {
        $config = $service->provider->getApiConfigArray();
        $pinnedLocation = (string) ($config['location_id'] ?? '');

        if ($pinnedLocation === '') {
            return 'ignored';
        }

        $locationGid = $this->gidFrom(null, $payload['location_id'] ?? null, 'Location');

        if ($locationGid !== '' && $locationGid !== $pinnedLocation) {
            return 'ignored';
        }

        /*
         * Built from the numeric id only. An inventory_levels payload puts the
         * *InventoryLevel* GID in admin_graphql_api_id — and with a query
         * string attached, e.g.
         *   gid://shopify/InventoryLevel/152826249259?inventory_item_id=47216940875819
         * Feeding that to inventoryItem(id:) makes Shopify reject the call with
         * "Invalid id", which is how these events dead-lettered in production.
         */
        $itemGid = $this->gidFrom(null, $payload['inventory_item_id'] ?? null, 'InventoryItem');

        if ($itemGid === '') {
            return 'ignored';
        }

        $data = $service->getAdapter()->getClient()->query(
            ShopifyGraphQL::INVENTORY_LEVEL_BY_ITEM,
            ['itemId' => $itemGid, 'locationId' => $pinnedLocation]
        );

        $item = data_get($data, 'inventoryItem');
        $level = data_get($item, 'inventoryLevel');

        // A disconnect leaves the item with no level at this location.
        if (! is_array($item) || ! is_array($level)) {
            return 'gone';
        }

        $quantities = [];

        foreach (data_get($level, 'quantities', []) as $quantity) {
            $quantities[(string) data_get($quantity, 'name')] = (int) data_get($quantity, 'quantity', 0);
        }

        return $service->applier()->applyInventoryLevel([
            'external_inventory_item_id' => data_get($item, 'id'),
            'external_variant_id' => data_get($item, 'variant.id'),
            'sku' => data_get($item, 'sku'),
            'available' => $quantities['available'] ?? 0,
            'on_hand' => $quantities['on_hand'] ?? null,
            'committed' => $quantities['committed'] ?? null,
            'external_updated_at' => $service->getAdapter()->parseExternalDate(data_get($level, 'updatedAt')),
            'location_id' => $pinnedLocation,
        ]);
    }

    // -------------------------------------------------------------------------
    // Lifecycle and compliance
    // -------------------------------------------------------------------------

    /**
     * The merchant removed the app: the token is dead and syncing must stop.
     */
    private function appUninstalled(ShopifyService $service): string
    {
        $service->provider->updateQuietly(['is_active' => false]);

        Log::warning(__METHOD__.' - Shopify app uninstalled; connection deactivated', [
            'provider_id' => $service->provider->id,
            'shop' => data_get($service->provider->getApiConfigArray(), 'shop_domain'),
        ]);

        return 'deactivated';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scopesUpdated(ShopifyService $service, array $payload): string
    {
        $config = $service->provider->getApiConfigArray();
        $config['granted_scopes'] = implode(',', (array) data_get($payload, 'current', []));
        $config['scopes_updated_at'] = Carbon::now()->toIso8601String();

        $service->provider->updateQuietly(['api_config' => $config]);

        return 'scopes recorded';
    }

    /**
     * GDPR/CCPA: the merchant asked what we hold about a shopper.
     *
     * Shopify requires the app to respond to the merchant out of band within 30
     * days, so this records the request loudly rather than pretending to
     * fulfil it automatically.
     *
     * @param  array<string, mixed>  $payload
     */
    private function complianceDataRequest(ShopifyService $service, WebhookEvents $event, array $payload): string
    {
        Log::critical(__METHOD__.' - Shopify customer data request received; manual fulfilment required within 30 days', [
            'provider_id' => $service->provider->id,
            'webhook_event_id' => $event->id,
            'shop' => $event->shop_domain,
            'shopify_customer_id' => data_get($payload, 'customer.id'),
        ]);

        return 'logged for manual fulfilment';
    }

    /**
     * GDPR erasure for one shopper.
     *
     * The mapping always goes. The iam_users row only goes when it is one we
     * synthesised for this shop and nothing else references it — it is a
     * platform identity that may belong to another tenant or own records of
     * its own, and deleting somebody else's user because one merchant redacted
     * their copy of a contact would be a far worse bug than an orphan row.
     *
     * @param  array<string, mixed>  $payload
     */
    private function complianceCustomerRedact(ShopifyService $service, array $payload): string
    {
        $gid = $this->gidFrom(
            data_get($payload, 'customer.admin_graphql_api_id'),
            data_get($payload, 'customer.id'),
            'Customer'
        );

        if ($gid === '') {
            return 'ignored';
        }

        $outcome = $service->applier()->deleteCustomerMapping($gid);

        Log::warning(__METHOD__.' - Shopify customer redaction processed', [
            'provider_id' => $service->provider->id,
            'external_customer_id' => $gid,
            'mapping' => $outcome,
        ]);

        return 'redacted: '.$outcome;
    }

    /**
     * The merchant uninstalled 48h+ ago and Shopify asks us to erase shop data.
     *
     * Deliberately stops at deactivation and a critical log. Erasure here would
     * delete synced catalogue and order history that the merchant's invoices
     * and our accounting records point at, and no webhook should be able to
     * trigger that unattended. Ops runs the erasure explicitly.
     */
    private function complianceShopRedact(ShopifyService $service): string
    {
        $service->provider->updateQuietly(['is_active' => false]);

        Log::critical(__METHOD__.' - Shopify shop redaction requested; connection deactivated, DATA ERASURE PENDING OPERATOR ACTION', [
            'provider_id' => $service->provider->id,
            'shop' => data_get($service->provider->getApiConfigArray(), 'shop_domain'),
        ]);

        return 'deactivated; erasure pending operator action';
    }

    // -------------------------------------------------------------------------
    // Plumbing
    // -------------------------------------------------------------------------

    private function serviceFor(int $providerId): ?ShopifyService
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($providerId);

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'shopify') {
            return null;
        }

        // The inbox is filled by an unauthenticated endpoint, so there is no
        // user in context; act as the connection's own owner, with the write
        // policies bypassed for the same reason the sync jobs bypass them.
        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);
        UserHelper::bypassRolesCheck(true);

        return new ShopifyService($provider);
    }

    /**
     * The GID for the resource a webhook is about.
     *
     * Shopify's payloads carry admin_graphql_api_id on most resources and a
     * bare numeric id on all of them, so the GID is preferred and rebuilt from
     * the numeric id otherwise.
     *
     * @param  array<string, mixed>  $payload
     */
    private function gid(array $payload, string $type): string
    {
        return $this->gidFrom($payload['admin_graphql_api_id'] ?? null, $payload['id'] ?? null, $type);
    }

    private function gidFrom(mixed $gid, mixed $numericId, string $type): string
    {
        if (is_string($gid) && str_starts_with($gid, 'gid://')) {
            return $gid;
        }

        if (is_int($numericId) || (is_string($numericId) && $numericId !== '' && ctype_digit($numericId))) {
            return 'gid://shopify/'.$type.'/'.$numericId;
        }

        return '';
    }

    private function markProcessed(WebhookEvents $event, string $outcome): void
    {
        $event->updateQuietly([
            'is_processed' => true,
            'processed_at' => Carbon::now(),
            'attempts' => (int) $event->attempts + 1,
            'error_message' => null,
            'headers' => array_merge((array) $event->headers, ['_outcome' => $outcome]),
        ]);
    }

    private function markFailed(WebhookEvents $event, \Throwable $e): void
    {
        $attempts = (int) $event->attempts + 1;

        $event->updateQuietly([
            'attempts' => $attempts,
            'error_message' => mb_substr($e->getMessage(), 0, 2000),
        ]);

        $context = [
            'webhook_event_id' => $event->id,
            'provider_id' => $event->marketplace_provider_id,
            'topic' => $event->topic,
            'attempts' => $attempts,
            'error' => $e->getMessage(),
        ];

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Parked rather than dropped: the row stays queryable as a dead
            // letter so an operator can see what was lost and replay it.
            Log::error(static::class.' - webhook event parked as dead letter', $context);

            return;
        }

        Log::warning(static::class.' - webhook event failed, will retry', $context);
    }
}
