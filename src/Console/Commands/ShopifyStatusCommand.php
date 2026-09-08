<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Database\Models\ProviderSyncStates;
use NextDeveloper\Marketplace\Database\Models\WebhookEvents;
use NextDeveloper\Marketplace\Jobs\Shopify\ProcessShopifyWebhookJob;

/**
 * What is actually syncing, per connection and per entity.
 *
 * The defining complaint about integrations of this kind is that a green "On"
 * badge only proves the connection exists, not that anything is flowing — one
 * audited tenant had 322 orders in sync against 8,712 silently failing. This
 * is the answer to that: per-entity cursors, failure counts, the last error
 * verbatim, and the webhook backlog, including events that have exhausted
 * their attempts and are sitting in the dead-letter state.
 */
class ShopifyStatusCommand extends Command
{
    protected $signature = 'marketplace:shopify-status
                            {--provider= : Restrict to one provider (id or uuid)}
                            {--json : Emit machine-readable output}';

    protected $description = 'Show sync health for connected Shopify shops';

    public function handle(): int
    {
        $providers = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw("lower(trim(adapter)) = 'shopify'")
            ->when($this->option('provider'), function ($q) {
                $ref = $this->option('provider');

                return is_numeric($ref) ? $q->where('id', (int) $ref) : $q->where('uuid', $ref);
            })
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            $this->info('No Shopify providers configured.');

            return self::SUCCESS;
        }

        $report = [];

        foreach ($providers as $provider) {
            $report[] = $this->describe($provider);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($report as $entry) {
            $this->render($entry);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Providers $provider): array
    {
        $config = $provider->getApiConfigArray();

        $states = ProviderSyncStates::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->get()
            ->keyBy('entity_type');

        $entities = [];

        foreach (['products', 'inventory', 'orders', 'customers'] as $entity) {
            $state = $states->get($entity);

            $entities[$entity] = [
                'cursor' => $state?->cursor_updated_at,
                'last_success' => $state?->last_success_at,
                'consecutive_failures' => (int) ($state?->consecutive_failures ?? 0),
                'records_processed' => (int) ($state?->records_processed ?? 0),
                'last_error' => $state?->last_error,
            ];
        }

        $webhooks = [
            'pending' => WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $provider->id)
                ->where('is_processed', false)
                ->where('attempts', '<', ProcessShopifyWebhookJob::MAX_ATTEMPTS)
                ->count(),
            'dead_letters' => WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $provider->id)
                ->where('is_processed', false)
                ->where('attempts', '>=', ProcessShopifyWebhookJob::MAX_ATTEMPTS)
                ->count(),
            'processed_24h' => WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $provider->id)
                ->where('is_processed', true)
                ->where('processed_at', '>=', now()->subDay())
                ->count(),
        ];

        $mapped = [
            'products' => DB::table('marketplace_product_mappings')
                ->where('marketplace_provider_id', $provider->id)->whereNull('deleted_at')->count(),
            'variants' => DB::table('marketplace_product_catalog_mappings')
                ->where('marketplace_provider_id', $provider->id)->whereNull('deleted_at')->count(),
            'customers' => DB::table('marketplace_customer_mappings')
                ->where('marketplace_provider_id', $provider->id)->whereNull('deleted_at')->count(),
            'orders' => DB::table('marketplace_orders')
                ->where('marketplace_provider_id', $provider->id)->whereNull('deleted_at')->count(),
        ];

        $failingOrders = DB::table('marketplace_orders')
            ->where('marketplace_provider_id', $provider->id)
            ->whereNotNull('sync_error_message')
            ->whereNull('deleted_at')
            ->count();

        return [
            'provider_id' => $provider->id,
            'name' => $provider->name,
            'shop' => $config['shop_domain'] ?? null,
            'is_active' => (bool) $provider->is_active,
            'has_token' => $provider->getDecryptedAccessToken() !== null,
            'api_version' => $config['api_version'] ?? null,
            'location_id' => $config['location_id'] ?? null,
            'entities' => $entities,
            'webhooks' => $webhooks,
            'mapped' => $mapped,
            'orders_with_sync_errors' => $failingOrders,
        ];
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function render(array $e): void
    {
        $badge = $e['is_active'] ? '<info>active</info>' : '<comment>PAUSED</comment>';

        $this->line('');
        $this->line("<info>{$e['name']}</info> (id {$e['provider_id']}, {$e['shop']}) — {$badge}"
            .($e['has_token'] ? '' : ' <comment>NO TOKEN</comment>'));

        $this->line(sprintf(
            '  mapped: %d products / %d variants / %d customers / %d orders',
            $e['mapped']['products'], $e['mapped']['variants'], $e['mapped']['customers'], $e['mapped']['orders']
        ));

        foreach ($e['entities'] as $entity => $state) {
            $flag = $state['consecutive_failures'] > 0 ? '  <comment>FAILING x'.$state['consecutive_failures'].'</comment>' : '';

            $this->line(sprintf(
                '  %-10s cursor=%-26s last_ok=%-26s processed=%d%s',
                $entity,
                $state['cursor'] ?? 'never',
                $state['last_success'] ?? 'never',
                $state['records_processed'],
                $flag
            ));

            if ($state['last_error']) {
                $this->line('             last error: '.mb_substr($state['last_error'], 0, 120));
            }
        }

        $this->line(sprintf(
            '  webhooks: %d pending, %d processed in 24h%s',
            $e['webhooks']['pending'],
            $e['webhooks']['processed_24h'],
            $e['webhooks']['dead_letters'] > 0
                ? ', <comment>'.$e['webhooks']['dead_letters'].' DEAD-LETTERED</comment>'
                : ''
        ));

        if ($e['orders_with_sync_errors'] > 0) {
            $this->warn('  '.$e['orders_with_sync_errors'].' order(s) carry a sync_error_message');
        }
    }
}
