<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Exceptions\NotFoundException;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Markets;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Database\Models\ProviderSyncStates;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyAdapter;

/**
 * Orchestrates one Shopify shop connection.
 *
 * Mirrors TrendyolGoYemekService: constructed with a Providers row, owns the
 * adapter, and is the object the console command and the queue jobs talk to.
 *
 * What it adds over the Trendyol service is durable sync state. The existing
 * pull loop takes its cursor from a CLI flag defaulting to now(), so a missed
 * run silently drops that window. Here each entity's cursor lives in
 * marketplace_provider_sync_states and only advances on success.
 */
class ShopifyService
{
    /**
     * Consecutive failures before the connection is paused and the merchant
     * told. Matches the S3 webhook deliverer's threshold.
     */
    public const AUTO_PAUSE_THRESHOLD = 5;

    /**
     * Re-scan this far behind the cursor on every delta query.
     *
     * Shopify's updated_at is a server timestamp, so a record written while we
     * were walking a page boundary can otherwise be stepped over and never seen
     * again.
     */
    public const CURSOR_OVERLAP_MINUTES = 15;

    /**
     * How far back a first-ever sync reaches when no cursor exists yet.
     *
     * Orders are capped at 60 days by Shopify unless read_all_orders has been
     * approved, so reaching further is pointless until it is.
     */
    public const INITIAL_LOOKBACK_DAYS = 60;

    private ShopifyAdapter $adapter;

    private ?Markets $market = null;

    private ?ShopifyApplyService $applier = null;

    /**
     * @throws NotFoundException
     */
    public function __construct(public Providers $provider)
    {
        if (! $this->provider->marketplace_market_id) {
            $message = "Provider '{$this->provider->name}' has no marketplace market.";
            Log::warning(__METHOD__." - {$message}");

            throw new NotFoundException($message);
        }

        $this->market = $this->loadMarket((int) $this->provider->marketplace_market_id);
        $this->adapter = new ShopifyAdapter($this->provider);
    }

    public function getAdapter(): ShopifyAdapter
    {
        return $this->adapter;
    }

    /**
     * The record applier shared by the scheduled pull and the webhook processor.
     *
     * Both paths must apply a record identically — same fingerprint, ordering
     * guard and quiet writes — so both go through one object rather than two
     * copies of the rules.
     */
    public function applier(): ShopifyApplyService
    {
        return $this->applier ??= new ShopifyApplyService($this->provider, $this->market?->common_currency_id);
    }

    public function getMarket(): ?Markets
    {
        return $this->market;
    }

    /**
     * Verify the stored credentials still work.
     */
    public function authenticate(): bool
    {
        return $this->adapter->authenticate([]);
    }

    // -------------------------------------------------------------------------
    // Sync state
    // -------------------------------------------------------------------------

    /**
     * The sync-state row for one entity, created on first use.
     *
     * Reads drop AuthorizationScope: this runs on a queue with no user context,
     * and the row belongs to the provider rather than to whoever triggered it.
     */
    public function syncState(string $entityType): ProviderSyncStates
    {
        $state = ProviderSyncStates::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_provider_id', $this->provider->id)
            ->where('entity_type', $entityType)
            ->first();

        if ($state) {
            return $state;
        }

        return ProviderSyncStates::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->create([
                'marketplace_provider_id' => $this->provider->id,
                'entity_type' => $entityType,
                'iam_account_id' => $this->provider->iam_account_id,
                'iam_user_id' => $this->provider->iam_user_id,
            ]);
    }

    /**
     * Where a delta query for this entity should start.
     */
    public function cursorFor(string $entityType): Carbon
    {
        $state = $this->syncState($entityType);

        if ($state->cursor_updated_at === null) {
            return Carbon::now()->subDays(self::INITIAL_LOOKBACK_DAYS);
        }

        return Carbon::parse($state->cursor_updated_at)->subMinutes(self::CURSOR_OVERLAP_MINUTES);
    }

    /**
     * Record a successful run and advance the cursor.
     *
     * The cursor only ever moves forward, so an out-of-order or replayed batch
     * cannot rewind it and cause the next run to re-ingest everything.
     */
    public function markSynced(string $entityType, ?Carbon $highWatermark, int $recordsProcessed = 0): void
    {
        $state = $this->syncState($entityType);

        $cursor = $state->cursor_updated_at ? Carbon::parse($state->cursor_updated_at) : null;

        if ($highWatermark !== null && ($cursor === null || $highWatermark->greaterThan($cursor))) {
            $cursor = $highWatermark;
        }

        $state->updateQuietly([
            'cursor_updated_at' => $cursor,
            'last_run_at' => Carbon::now(),
            'last_success_at' => Carbon::now(),
            'consecutive_failures' => 0,
            'last_error' => null,
            'records_processed' => (int) $state->records_processed + $recordsProcessed,
        ]);
    }

    /**
     * Record a failed run, pausing the connection once failures pile up.
     *
     * Silent sync death is the single most common complaint about integrations
     * of this kind, so the pause is paired with a loud log and is meant to be
     * surfaced to the merchant.
     */
    public function markFailed(string $entityType, string $error): void
    {
        $state = $this->syncState($entityType);

        $failures = (int) $state->consecutive_failures + 1;

        $state->updateQuietly([
            'last_run_at' => Carbon::now(),
            'consecutive_failures' => $failures,
            'last_error' => mb_substr($error, 0, 2000),
        ]);

        Log::error(__METHOD__.' - Shopify sync failed', [
            'provider_id' => $this->provider->id,
            'entity' => $entityType,
            'consecutive_failures' => $failures,
            'error' => $error,
        ]);

        if ($failures >= self::AUTO_PAUSE_THRESHOLD && $this->provider->is_active) {
            $this->provider->updateQuietly(['is_active' => false]);

            Log::critical(__METHOD__.' - Shopify connection paused after repeated failures', [
                'provider_id' => $this->provider->id,
                'shop' => $this->adapter->getClient()->getShopDomain(),
                'entity' => $entityType,
                'failures' => $failures,
            ]);
        }
    }

    /**
     * @throws NotFoundException
     */
    private function loadMarket(int $marketId): Markets
    {
        $market = Markets::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)->find($marketId);

        if (! $market) {
            $message = "Market with ID '{$marketId}' not found.";
            Log::warning(__METHOD__." - {$message}");

            throw new NotFoundException($message);
        }

        return $market;
    }
}
