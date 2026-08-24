<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyWebhookRegistrar;

/**
 * Re-registers webhook subscriptions Shopify has silently dropped.
 *
 * Shopify deletes a subscription after eight consecutive delivery failures and
 * notifies only the app's emergency email, so without this a brief outage turns
 * into permanent, unnoticed data loss for that merchant.
 */
class ShopifyWebhookHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    public int $timeout = 600;

    public int $tries = 1;

    /** @var array<int, array<string, mixed>> */
    public array $report = [];

    public function __construct(public ?int $providerId = null, public bool $dryRun = false)
    {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(): void
    {
        $providers = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->whereRaw("lower(trim(adapter)) = 'shopify'")
            ->where('is_active', true)
            ->when($this->providerId, fn ($q) => $q->where('id', $this->providerId))
            ->get();

        foreach ($providers as $provider) {
            UserHelper::setUserById($provider->iam_user_id);
            UserHelper::setCurrentAccountById($provider->iam_account_id);
            UserHelper::bypassRolesCheck(true);

            try {
                $result = (new ShopifyWebhookRegistrar(new ShopifyService($provider)))
                    ->reconcile(dryRun: $this->dryRun);

                $this->report[] = ['provider_id' => $provider->id] + $result;

                if ($result['created'] !== []) {
                    Log::warning(__METHOD__.' - re-registered webhook subscriptions Shopify had dropped', [
                        'provider_id' => $provider->id,
                        'topics' => $result['created'],
                    ]);
                }
            } catch (\Throwable $e) {
                $this->report[] = ['provider_id' => $provider->id, 'error' => $e->getMessage()];

                Log::error(__METHOD__.' - webhook health check failed', [
                    'provider_id' => $provider->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
