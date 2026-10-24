<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Jobs\Ebay\AbstractEbaySyncJob;
use NextDeveloper\Marketplace\Jobs\Ebay\SyncEbayInventoryJob;
use NextDeveloper\Marketplace\Jobs\Ebay\SyncEbayProductsJob;

/**
 * Entry point for the eBay pull sync. eBay has no delta, so a run is a full inventory scan. The scheduler runs it per entity with --queue;
 * operators can run it by hand with --provider / --full / --dry-run.
 */
class SyncEbayCommand extends Command
{
    protected $signature = 'marketplace:sync-ebay
                            {--entity=all : products|inventory|all}
                            {--provider= : Only this provider (id or uuid)}
                            {--full : Restart the scan from the first page instead of resuming an interrupted one}
                            {--dry-run : Report what would change without writing anything}
                            {--queue : Dispatch jobs to the marketplace-sync queue instead of running inline}';

    protected $description = 'Pull products and inventory from connected eBay seller accounts';

    /**
     * Ordered so products land before inventory references them.
     *
     * @var array<string, class-string<AbstractEbaySyncJob>>
     */
    public const ENTITY_JOBS = [
        'products' => SyncEbayProductsJob::class,
        'inventory' => SyncEbayInventoryJob::class,
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
            $this->info('No active eBay providers found.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($providers as $provider) {
            $this->line("<info>{$provider->name}</info> (id {$provider->id}, ".data_get($provider->getApiConfigArray(), 'ebay.seller_username', '?').')');

            foreach ($entities as $entityType) {
                $jobClass = self::ENTITY_JOBS[$entityType];

                /** @var AbstractEbaySyncJob $job */
                $job = new $jobClass($provider->id, $dryRun, $full);

                if ($queue) {
                    dispatch($job);
                    $this->line("  {$entityType}: queued on ".AbstractEbaySyncJob::QUEUE_NAME);

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
            ->whereRaw("lower(trim(adapter)) = 'ebay'")
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
