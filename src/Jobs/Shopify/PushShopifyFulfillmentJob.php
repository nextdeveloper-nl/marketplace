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
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pushes local fulfilment and cancellation back to Shopify.
 *
 * This is the write-back half merchants actually notice: an order marked
 * shipped in leo has to stop looking unfulfilled in the Shopify admin, or the
 * merchant ships it twice.
 *
 * Reconciling, like the inventory push: it compares the local status against
 * the remote status recorded on the order and acts on the difference, so a
 * missed run retries rather than losing the transition, and a redelivery cannot
 * fulfil twice. The adapter's mutations carry deterministic idempotency keys,
 * which is what makes a retried refund pay the customer once.
 */
class PushShopifyFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Local statuses that mean "this has shipped".
     */
    public const FULFILLED_STATUSES = ['dispatched', 'delivered', 'fulfilled', 'shipped'];

    public int $timeout = 900;

    public int $tries = 1;

    /** @var array<string, mixed> */
    public array $report = [];

    public function __construct(public int $providerId, public bool $dryRun = false) {}

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($this->providerId);

        if (! $provider || ! $provider->is_active) {
            $this->report = ['skipped' => 'provider missing or paused'];

            return;
        }

        if (! $provider->canPush('fulfillment')) {
            $this->report = ['skipped' => 'sync_policy.fulfillment.direction does not allow push'];

            return;
        }

        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);
        UserHelper::bypassRolesCheck(true);

        $service = new ShopifyService($provider);
        $adapter = $service->getAdapter();

        $fulfilled = $cancelled = $skipped = $failed = 0;
        $samples = [];

        $orders = Orders::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $provider->id)
            ->whereNotNull('external_order_id')
            ->whereIn('status', array_merge(self::FULFILLED_STATUSES, ['cancelled', 'canceled']))
            ->get();

        foreach ($orders as $order) {
            $status = strtolower((string) $order->status);
            $remoteFulfillment = (string) data_get($order->marketplace_metadata, 'fulfillment_status');
            $wantsCancel = in_array($status, ['cancelled', 'canceled'], true);

            $action = match (true) {
                $wantsCancel && $order->cancelled_at === null => 'cancel',
                $wantsCancel => null,
                in_array($status, self::FULFILLED_STATUSES, true) && $remoteFulfillment !== 'FULFILLED' => 'fulfil',
                default => null,
            };

            if ($action === null) {
                $skipped++;

                continue;
            }

            if ($this->dryRun) {
                if (count($samples) < 25) {
                    $samples[] = $action.': '.$order->external_order_number.' (local '.$status.', shopify '.($remoteFulfillment ?: 'n/a').')';
                }
                $action === 'cancel' ? $cancelled++ : $fulfilled++;

                continue;
            }

            try {
                $action === 'cancel'
                    ? $adapter->cancelOrder($order)
                    : $adapter->createFulfillment($order);

                // Re-read rather than assume: Shopify decides what actually
                // happened (partial fulfilment, a cancel it refused), and the
                // order row should record that, not our intent.
                $this->refresh($service, $order);

                $action === 'cancel' ? $cancelled++ : $fulfilled++;
            } catch (\Throwable $e) {
                $failed++;

                $order->updateQuietly(['sync_error_message' => mb_substr($e->getMessage(), 0, 1000)]);

                Log::error(__METHOD__.' - fulfilment push failed', [
                    'provider_id' => $provider->id,
                    'order_id' => $order->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->report = array_filter([
            'provider_id' => $provider->id,
            'fulfilled' => $fulfilled,
            'cancelled' => $cancelled,
            'nothing_to_do' => $skipped,
            'failed' => $failed,
            'dry_run' => $this->dryRun,
            'would_push' => $this->dryRun ? $samples : null,
        ], fn ($v) => $v !== null);

        Log::info(__METHOD__.' - fulfilment push completed', $this->report);
    }

    private function refresh(ShopifyService $service, Orders $order): void
    {
        $data = $service->getAdapter()->getClient()->query(
            ShopifyGraphQL::ORDER_BY_ID,
            ['id' => $order->external_order_id]
        );

        $node = data_get($data, 'order');

        if (is_array($node)) {
            $service->applier()->applyOrder($service->getAdapter()->normalizeOrderData($node));

            return;
        }

        $order->updateQuietly(['last_synced_at' => Carbon::now()]);
    }
}
