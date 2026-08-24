<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Jobs\Shopify\PushShopifyFulfillmentJob;
use NextDeveloper\Marketplace\Jobs\Shopify\PushShopifyInventoryJob;

/**
 * Pushes local changes back to Shopify: stock (compare-and-swap) and
 * fulfilment/cancellation.
 *
 * Product push is deliberately absent. It is implemented in the adapter but
 * gated behind an approved dry-run per provider, because a mapping bug that
 * overwrites a merchant's live catalogue has no undo in Shopify.
 */
class PushShopifyCommand extends Command
{
    protected $signature = 'marketplace:push-shopify
                            {--entity=all : inventory|fulfillment|all}
                            {--provider= : Restrict to one provider (id or uuid)}
                            {--dry-run : Report what would be pushed without writing}
                            {--queue : Dispatch to the marketplace-sync queue instead of running inline}';

    protected $description = 'Push locally-changed stock and fulfilment state to connected Shopify shops';

    public function handle(): int
    {
        $entity = strtolower((string) $this->option('entity'));

        if (! in_array($entity, ['inventory', 'fulfillment', 'all'], true)) {
            $this->error('Unknown entity "'.$entity.'". Use inventory|fulfillment|all.');

            return self::FAILURE;
        }

        $providers = $this->providers();

        if ($providers->isEmpty()) {
            $this->info('No active Shopify providers found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $queue = (bool) $this->option('queue') && ! $dryRun;
        $failures = 0;

        foreach ($providers as $provider) {
            $this->line("<info>{$provider->name}</info> (id {$provider->id})");

            $jobs = [];

            if ($entity === 'inventory' || $entity === 'all') {
                $jobs['inventory'] = new PushShopifyInventoryJob($provider->id, $dryRun);
            }

            if ($entity === 'fulfillment' || $entity === 'all') {
                $jobs['fulfillment'] = new PushShopifyFulfillmentJob($provider->id, $dryRun);
            }

            foreach ($jobs as $label => $job) {
                if ($queue) {
                    dispatch($job);
                    $this->line("  {$label}: queued");

                    continue;
                }

                try {
                    $job->handle();
                    $this->renderReport($label, $job->report);
                } catch (\Throwable $e) {
                    $failures++;
                    $this->error("  {$label}: FAILED — ".$e->getMessage());
                }
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(string $label, array $report): void
    {
        if (isset($report['skipped'])) {
            $this->line("  {$label}: skipped — {$report['skipped']}");

            return;
        }

        $summary = collect($report)
            ->except(['provider_id', 'would_push', 'dry_run'])
            ->map(fn ($v, $k) => "$k=$v")
            ->implode(' ');

        $this->line('  '.$label.': '.(! empty($report['dry_run']) ? '[DRY-RUN] ' : '').$summary);

        foreach ((array) ($report['would_push'] ?? []) as $line) {
            $this->line('    · '.$line);
        }
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
}
