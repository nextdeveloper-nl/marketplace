<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Jobs\Shopify\PushShopifyFulfillmentJob;
use NextDeveloper\Marketplace\Jobs\Shopify\PushShopifyInventoryJob;
use NextDeveloper\Marketplace\Jobs\Shopify\PushShopifyProductsJob;

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
                            {--entity=all : inventory|fulfillment|products|all}
                            {--provider= : Restrict to one provider (id or uuid)}
                            {--dry-run : Report what would be pushed without writing}
                            {--approve : Enable catalogue push for this provider, after reviewing a dry-run}
                            {--revoke : Disable catalogue push for this provider}
                            {--force : Override the catalogue-push blast-radius guard}
                            {--queue : Dispatch to the marketplace-sync queue instead of running inline}';

    protected $description = 'Push locally-changed stock and fulfilment state to connected Shopify shops';

    public function handle(): int
    {
        $entity = strtolower((string) $this->option('entity'));

        if (! in_array($entity, ['inventory', 'fulfillment', 'products', 'all'], true)) {
            $this->error('Unknown entity "'.$entity.'". Use inventory|fulfillment|products|all.');

            return self::FAILURE;
        }

        $providers = $this->providers();

        if ($providers->isEmpty()) {
            $this->info('No active Shopify providers found.');

            return self::SUCCESS;
        }

        if ($this->option('approve') || $this->option('revoke')) {
            return $this->setCataloguePushApproval($this->providers(), (bool) $this->option('approve'));
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

            /*
             * Catalogue push is asked for by name. "all" deliberately excludes
             * it: a scheduled sweep that can rewrite a merchant's catalogue
             * should never be something you enable by accident.
             */
            if ($entity === 'products') {
                $jobs['products'] = new PushShopifyProductsJob($provider->id, $dryRun, (bool) $this->option('force'));
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
            ->except(['provider_id', 'would_push', 'dry_run', 'diffs', 'aborted', 'reason'])
            ->map(fn ($v, $k) => "$k=$v")
            ->implode(' ');

        $this->line('  '.$label.': '.(! empty($report['dry_run']) ? '[DRY-RUN] ' : '').$summary);

        foreach ((array) ($report['would_push'] ?? []) as $line) {
            $this->line('    · '.$line);
        }

        if (! empty($report['aborted'])) {
            $this->error('  ABORTED — '.$report['reason']);
        }

        foreach ((array) ($report['diffs'] ?? []) as $diff) {
            $this->line('    · <info>'.$diff['product'].'</info>');

            foreach ($diff['changes'] as $field => $sides) {
                $this->line(sprintf('        %-12s shopify: %s', $field, $sides['shopify']));
                $this->line(sprintf('        %-12s ours   : %s', '', $sides['ours']));
            }
        }

        if (! empty($report['dry_run']) && ($report['pushed'] ?? 0) > 0 && isset($report['mapped'])) {
            $this->line('');
            $this->warn('  Nothing was written. To allow these writes from now on:');
            $this->line('    php artisan marketplace:push-shopify --entity=products --provider='
                .$report['provider_id'].' --approve');
        }
    }

    /**
     * Turn catalogue push on or off for a connection.
     *
     * Approval requires a dry-run within the last half hour, so nobody can
     * enable a catalogue-rewriting capability without having just looked at
     * what it would rewrite.
     *
     * @param  Collection<int, Providers>  $providers
     */
    private function setCataloguePushApproval($providers, bool $approve): int
    {
        foreach ($providers as $provider) {
            $config = $provider->getApiConfigArray();

            if (! $approve) {
                unset($config['product_push']['approved_at'], $config['product_push']['approved_by']);
                $provider->updateQuietly(['api_config' => $config]);
                $this->info("Catalogue push revoked for {$provider->name} (id {$provider->id}).");

                continue;
            }

            $dryRunAt = data_get($config, 'product_push.dry_run_at');

            if ($dryRunAt === null || Carbon::parse($dryRunAt)->lt(Carbon::now()->subMinutes(30))) {
                $this->error("No recent dry-run for {$provider->name} (id {$provider->id}).");
                $this->line('  Review what would change first:');
                $this->line('    php artisan marketplace:push-shopify --entity=products --provider='
                    .$provider->id.' --dry-run');

                return self::FAILURE;
            }

            $config['product_push']['approved_at'] = Carbon::now()->toIso8601String();
            $config['product_push']['approved_by'] = optional(UserHelper::me())->uuid ?? 'console';
            $provider->updateQuietly(['api_config' => $config]);

            $this->info("Catalogue push APPROVED for {$provider->name} (id {$provider->id}).");
            $this->line('  Reviewed diff covered '.data_get($config, 'product_push.dry_run_would_change', '?')
                .' product(s) at '.$dryRunAt);
        }

        return self::SUCCESS;
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
