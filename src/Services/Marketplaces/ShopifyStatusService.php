<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Facades\DB;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Database\Models\ProviderSyncStates;
use NextDeveloper\Marketplace\Database\Models\WebhookEvents;
use NextDeveloper\Marketplace\Jobs\Shopify\ProcessShopifyWebhookJob;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyAdapter;

/**
 * Added: one place that answers "why is this Shopify connection not working".
 *
 * Shared by the marketplace:shopify-status command and the status API endpoint, so the
 * operator and the customer see the same facts. The caller is responsible for having
 * authorised access to the provider (the API goes through ProvidersService::getByRef, which
 * applies AuthorizationScope); the queries here drop the scopes because the sync-state and
 * webhook rows belong to the provider, not to the requesting user.
 */
class ShopifyStatusService
{
    public const ENTITIES = ['products', 'inventory', 'orders', 'customers'];

    /** How many recent failed webhook events the report carries. */
    private const FAILED_EVENT_LIMIT = 10;

    /**
     * Stored facts only (no call to Shopify): cursors, failures, webhook backlog, mapping counts.
     *
     * @return array<string, mixed>
     */
    public static function describe(Providers $provider): array
    {
        $config = $provider->getApiConfigArray();

        $states = ProviderSyncStates::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->get()
            ->keyBy('entity_type');

        $entities = [];

        foreach (self::ENTITIES as $entity) {
            $state = $states->get($entity);

            $entities[$entity] = [
                'cursor' => $state?->cursor_updated_at,
                'last_run' => $state?->last_run_at,
                'last_success' => $state?->last_success_at,
                'consecutive_failures' => (int) ($state?->consecutive_failures ?? 0),
                'records_processed' => (int) ($state?->records_processed ?? 0),
                'last_error' => $state?->last_error,
            ];
        }

        $events = fn () => WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id);

        $webhooks = [
            'pending' => $events()->where('is_processed', false)
                ->where('attempts', '<', ProcessShopifyWebhookJob::MAX_ATTEMPTS)->count(),
            'dead_letters' => $events()->where('is_processed', false)
                ->where('attempts', '>=', ProcessShopifyWebhookJob::MAX_ATTEMPTS)->count(),
            'processed_24h' => $events()->where('is_processed', true)
                ->where('processed_at', '>=', now()->subDay())->count(),
        ];

        // Payload and headers are deliberately left out: they hold customer data.
        $recentFailures = $events()->where('is_processed', false)
            ->whereNotNull('error_message')
            ->orderByDesc('id')
            ->limit(self::FAILED_EVENT_LIMIT)
            ->get(['topic', 'external_event_id', 'attempts', 'error_message', 'triggered_at', 'updated_at'])
            ->map(fn ($e) => [
                'topic' => $e->topic,
                'event_id' => $e->external_event_id,
                'attempts' => (int) $e->attempts,
                'dead_lettered' => (int) $e->attempts >= ProcessShopifyWebhookJob::MAX_ATTEMPTS,
                'error' => mb_substr((string) $e->error_message, 0, 500),
                'triggered_at' => $e->triggered_at,
                'last_attempt_at' => $e->updated_at,
            ])
            ->all();

        $count = fn (string $table) => DB::table($table)
            ->where('marketplace_provider_id', $provider->id)->whereNull('deleted_at')->count();

        $mapped = [
            'products' => $count('marketplace_product_mappings'),
            'variants' => $count('marketplace_product_catalog_mappings'),
            'customers' => $count('marketplace_customer_mappings'),
            'orders' => $count('marketplace_orders'),
        ];

        $failingOrders = DB::table('marketplace_orders')
            ->where('marketplace_provider_id', $provider->id)
            ->whereNotNull('sync_error_message')
            ->whereNull('deleted_at')
            ->count();

        return [
            'provider_id' => $provider->id,
            'provider_uuid' => $provider->uuid,
            'name' => $provider->name,
            'shop' => $config['shop_domain'] ?? null,
            'is_active' => (bool) $provider->is_active,
            'alerting_muted' => $provider->isAlertingMuted(),
            'alerting_mute_reason' => $provider->alertingMuteReason(),
            'has_token' => $provider->getDecryptedAccessToken() !== null,
            'api_version' => $config['api_version'] ?? null,
            'location_id' => $config['location_id'] ?? null,
            'entities' => $entities,
            'webhooks' => $webhooks,
            'recent_failed_webhooks' => $recentFailures,
            'mapped' => $mapped,
            'orders_with_sync_errors' => $failingOrders,
        ];
    }

    /**
     * Calls Shopify: is the stored token accepted, and are our webhook subscriptions still
     * registered. Never throws; a failure is the finding and is returned as text.
     *
     * @return array<string, mixed>
     */
    public static function liveCheck(Providers $provider): array
    {
        $result = ['token_valid' => false, 'token_error' => null, 'webhooks' => null, 'webhooks_error' => null];

        try {
            $service = new ShopifyService($provider);

            $result['token_valid'] = $service->authenticate();
        } catch (\Throwable $e) {
            $result['token_error'] = mb_substr($e->getMessage(), 0, 500);

            return $result;
        }

        if (! $result['token_valid']) {
            $result['token_error'] = 'Shopify rejected the stored credentials; the shop must be reconnected.';

            return $result;
        }

        try {
            $registered = array_column((new ShopifyWebhookRegistrar($service))->list(), 'topic');
            $expected = ShopifyAdapter::WEBHOOK_TOPICS;

            // Shopify deletes a subscription after repeated delivery failures, silently.
            $result['webhooks'] = [
                'registered' => array_values(array_intersect($expected, $registered)),
                'missing' => array_values(array_diff($expected, $registered)),
            ];
        } catch (\Throwable $e) {
            $result['webhooks_error'] = mb_substr($e->getMessage(), 0, 500);
        }

        return $result;
    }
}
