<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Jobs\Shopify\AbstractShopifySyncJob;
use NextDeveloper\Marketplace\Jobs\Shopify\SyncShopifyCustomersJob;
use NextDeveloper\Marketplace\Jobs\Shopify\SyncShopifyInventoryJob;
use NextDeveloper\Marketplace\Jobs\Shopify\SyncShopifyOrdersJob;
use NextDeveloper\Marketplace\Jobs\Shopify\SyncShopifyProductsJob;

/**
 * Entry point for the Shopify pull sync.
 *
 * The scheduler runs it per entity (orders every 5 minutes, inventory every
 * 15, products/customers hourly — plan §14); operators run it by hand with
 * --provider / --full / --dry-run. Cursors live in
 * marketplace_provider_sync_states, so a missed run re-scans its window
 * instead of losing it.
 */
class SyncShopifyCommand extends Command
{
    protected $signature = 'marketplace:sync-shopify
                            {--entity=all : products|inventory|orders|customers|all}
                            {--provider= : Only this provider (id or uuid)}
                            {--full : Ignore the cursor and pull everything from the beginning}
                            {--dry-run : Report what would change without writing anything}
                            {--queue : Dispatch jobs to the marketplace-sync queue instead of running inline}';

    protected $description = 'Pull products, inventory, orders and customers from connected Shopify shops';

    /**
     * Ordered so a --full first run lands products before orders reference them.
     *
     * @var array<string, class-string<AbstractShopifySyncJob>>
     */
    private const ENTITY_JOBS = [
        'products' => SyncShopifyProductsJob::class,
        'inventory' => SyncShopifyInventoryJob::class,
        'customers' => SyncShopifyCustomersJob::class,
        'orders' => SyncShopifyOrdersJob::class,
    ];

    public function handle(): int
    {
        $entity = strtolower((string) $this->option('entity'));

        if ($entity !== 'all' && ! array_key_exists($entity, self::ENTITY_JOBS)) {
            $this->error('Unknown entity "'.$entity.'". Use one of: '.implode('|', array_keys(self::ENTITY_JOBS)).'|all.');

            return self::FAILURE;
        }

        $entities = $entity === 'all' ? array_keys(self::ENTITY_JOBS) : [$entity];

        $dryRun = (bool) $this->option('dry-run');
        $full = (bool) $this->option('full');
        $queue = (bool) $this->option('queue') && ! $dryRun;

        if ($this->option('queue') && $dryRun) {
            $this->warn('--dry-run runs inline so the report can be shown; ignoring --queue.');
        }

        $providers = $this->providers();

        if ($providers->isEmpty()) {
            $this->info('No active Shopify providers found.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($providers as $provider) {
            $this->line("<info>{$provider->name}</info> (id {$provider->id}, ".data_get($provider->getApiConfigArray(), 'shop_domain', '?').')');

            foreach ($entities as $entityType) {
                $jobClass = self::ENTITY_JOBS[$entityType];

                /** @var AbstractShopifySyncJob $job */
                $job = new $jobClass($provider->id, $dryRun, $full);

                if ($queue) {
                    dispatch($job);
                    $this->line("  {$entityType}: queued on ".AbstractShopifySyncJob::QUEUE_NAME);

                    continue;
                }

                try {
                    $job->handle();
                    $this->renderReport($entityType, $job->report);
                } catch (\Throwable $e) {
                    $failures++;
                    $this->error("  {$entityType}: FAILED — ".$e->getMessage());
                }
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Providers>
     */
    private function providers()
    {
        $query = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw("lower(trim(adapter)) = 'shopify'")
            ->where('is_active', true);

        if ($ref = $this->option('provider')) {
            is_numeric($ref) ? $query->where('id', (int) $ref) : $query->where('uuid', $ref);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(string $entityType, array $report): void
    {
        if (isset($report['skipped'])) {
            $this->line("  {$entityType}: skipped — {$report['skipped']}");

            return;
        }

        $details = collect($report['details'] ?? [])
            ->except('would_write')
            ->map(fn ($v, $k) => "$k=$v")
            ->implode(' ');

        $this->line(sprintf(
            '  %s: %s%d processed (since %s) %s',
            $entityType,
            ! empty($report['dry_run']) ? '[DRY-RUN] ' : '',
            $report['processed'] ?? 0,
            $report['since'] ?? '?',
            $details
        ));

        foreach ((array) data_get($report, 'details.would_write', []) as $line) {
            $this->line('    · '.$line);
        }
    }
}
