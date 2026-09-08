<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Database\Models\WebhookEvents;
use NextDeveloper\Marketplace\Jobs\Shopify\ProcessShopifyWebhookJob;
use NextDeveloper\Marketplace\Jobs\Shopify\ShopifyDeletionSweepJob;
use NextDeveloper\Marketplace\Jobs\Shopify\ShopifyWebhookHealthJob;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyWebhookRegistrar;

/**
 * Operates the Shopify webhook pipeline: registration, health, draining the
 * inbox, the deletion sweep, and the dead-letter view.
 *
 * The scheduler calls --process and --health; the rest is for operators.
 */
class ShopifyWebhooksCommand extends Command
{
    protected $signature = 'marketplace:shopify-webhooks
                            {--process : Drain the webhook inbox}
                            {--register : Create any missing webhook subscriptions}
                            {--health : Re-register subscriptions Shopify has dropped}
                            {--sweep : Diff the remote catalogue and remove products deleted in Shopify}
                            {--list : Show the subscriptions currently registered}
                            {--dead-letters : Show events that exhausted their attempts}
                            {--replay= : Reset an event id (or "all") back to unprocessed}
                            {--provider= : Restrict to one provider (id or uuid)}
                            {--dry-run : Report what would happen without writing}
                            {--batch=200 : Events to drain per run}';

    protected $description = 'Register, repair and process Shopify webhooks';

    public function handle(): int
    {
        $providers = $this->providers();

        if ($providers->isEmpty() && ! $this->option('process') && ! $this->option('replay')) {
            $this->info('No active Shopify providers found.');

            return self::SUCCESS;
        }

        $ranSomething = false;

        if ($this->option('replay')) {
            $this->replay((string) $this->option('replay'));
            $ranSomething = true;
        }

        if ($this->option('dead-letters')) {
            $this->deadLetters();
            $ranSomething = true;
        }

        if ($this->option('list')) {
            foreach ($providers as $provider) {
                $this->line("<info>{$provider->name}</info> (id {$provider->id})");
                $this->runFor($provider, function (ShopifyService $service) {
                    $registrar = new ShopifyWebhookRegistrar($service);
                    $subscriptions = $registrar->list();

                    if ($subscriptions === []) {
                        $this->line('  (no subscriptions registered)');

                        return;
                    }

                    foreach ($subscriptions as $subscription) {
                        $this->line(sprintf('  %-34s %s', $subscription['topic'], $subscription['callbackUrl'] ?? '?'));
                    }
                });
            }
            $ranSomething = true;
        }

        if ($this->option('register') || $this->option('health')) {
            if ($this->option('health') && ! $this->option('register')) {
                $job = new ShopifyWebhookHealthJob($providers->count() === 1 ? $providers->first()->id : null, (bool) $this->option('dry-run'));
                $job->handle();

                foreach ($job->report as $line) {
                    $this->renderReconcile($line);
                }
            } else {
                foreach ($providers as $provider) {
                    $this->line("<info>{$provider->name}</info> (id {$provider->id})");
                    $this->runFor($provider, function (ShopifyService $service) use ($provider) {
                        $result = (new ShopifyWebhookRegistrar($service))->reconcile(dryRun: (bool) $this->option('dry-run'));
                        $this->renderReconcile(['provider_id' => $provider->id] + $result);
                    });
                }
            }
            $ranSomething = true;
        }

        if ($this->option('sweep')) {
            $job = new ShopifyDeletionSweepJob($providers->count() === 1 ? $providers->first()->id : null, (bool) $this->option('dry-run'));
            $job->handle();

            foreach ($job->report as $line) {
                $this->line('  '.json_encode($line, JSON_UNESCAPED_SLASHES));
            }
            $ranSomething = true;
        }

        if ($this->option('process')) {
            $providerId = $this->option('provider') && $providers->count() === 1 ? $providers->first()->id : null;
            $job = new ProcessShopifyWebhookJob((int) $this->option('batch'), $providerId);
            $job->handle();

            $this->line('  '.json_encode($job->report, JSON_UNESCAPED_SLASHES));
            $ranSomething = true;
        }

        if (! $ranSomething) {
            $this->error('Nothing to do. Pass one of --process, --register, --health, --sweep, --list, --dead-letters, --replay.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Run a callback with the provider's own identity and the write policies
     * bypassed, the same context the queue jobs use.
     */
    private function runFor(Providers $provider, callable $callback): void
    {
        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);
        UserHelper::bypassRolesCheck(true);

        try {
            $callback(new ShopifyService($provider));
        } catch (\Throwable $e) {
            $this->error('  '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderReconcile(array $result): void
    {
        if (isset($result['error'])) {
            $this->error('  provider '.$result['provider_id'].': '.$result['error']);

            return;
        }

        $this->line('  endpoint: '.$result['callback_url']);
        $this->line('  already registered: '.count($result['existing']));
        $this->line('  created: '.(count($result['created']) ? implode(', ', $result['created']) : 'none'));

        foreach ($result['failed'] as $topic => $message) {
            $this->error("  failed {$topic}: {$message}");
        }

        foreach ($result['foreign'] as $subscription) {
            $this->warn('  other endpoint (left alone): '.$subscription['topic'].' -> '.($subscription['callbackUrl'] ?? '?'));
        }
    }

    private function deadLetters(): void
    {
        $events = WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('is_processed', false)
            ->where('attempts', '>=', ProcessShopifyWebhookJob::MAX_ATTEMPTS)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($events->isEmpty()) {
            $this->info('No dead-lettered webhook events.');

            return;
        }

        $this->warn($events->count().' dead-lettered event(s):');

        foreach ($events as $event) {
            $this->line(sprintf(
                '  #%d %-28s %s attempts=%d %s',
                $event->id,
                $event->topic,
                $event->shop_domain,
                $event->attempts,
                mb_substr((string) $event->error_message, 0, 90)
            ));
        }

        $this->line('Replay with: marketplace:shopify-webhooks --replay=<id|all> --process');
    }

    private function replay(string $which): void
    {
        $query = WebhookEvents::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->where('is_processed', false);

        if ($which !== 'all') {
            $query->whereIn('id', array_map('intval', explode(',', $which)));
        }

        $count = 0;

        foreach ($query->get() as $event) {
            $event->updateQuietly(['attempts' => 0, 'error_message' => null]);
            $count++;
        }

        $this->info("Reset {$count} event(s) for replay.");
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
