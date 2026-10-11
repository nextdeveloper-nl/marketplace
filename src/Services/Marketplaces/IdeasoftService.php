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
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ideasoft\IdeasoftAdapter;

/**
 * Orchestrates one Ideasoft store connection.
 *
 * Mirrors ShopifyService: constructed with a Providers row, owns the
 * adapter, and is the object the console command and any future queue jobs
 * talk to. Sync state (cursor, health, failure count) lives in
 * marketplace_provider_sync_states per entity, same as Shopify, rather than
 * a fire-and-forget CLI-flag cursor.
 */
class IdeasoftService
{
    /**
     * Consecutive failures before the connection is paused and the merchant
     * told. Matches ShopifyService::AUTO_PAUSE_THRESHOLD.
     */
    public const AUTO_PAUSE_THRESHOLD = 5;

    /**
     * Re-scan this far behind the cursor on every delta query, so a record
     * written while a page boundary was being walked is not stepped over.
     */
    public const CURSOR_OVERLAP_MINUTES = 15;

    /**
     * How far back a first-ever sync reaches when no cursor exists yet.
     */
    public const INITIAL_LOOKBACK_DAYS = 60;

    private IdeasoftAdapter $adapter;

    private ?Markets $market = null;

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
        $this->adapter = new IdeasoftAdapter($this->provider);
    }

    public function getAdapter(): IdeasoftAdapter
    {
        return $this->adapter;
    }

    public function getMarket(): ?Markets
    {
        return $this->market;
    }

    /**
     * Verify the stored credentials look usable. See IdeasoftAdapter::authenticate()
     * — this checks a token was saved, it is not a live call against Ideasoft yet.
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
     * Reads drop AuthorizationScope: this is meant to run on a queue with no
     * user context, and the row belongs to the provider rather than to
     * whoever triggered it.
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
     * Record a successful run and advance the cursor. The cursor only ever
     * moves forward, so a replayed batch cannot rewind it.
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

        Log::error(__METHOD__.' - Ideasoft sync failed', [
            'provider_id' => $this->provider->id,
            'entity' => $entityType,
            'consecutive_failures' => $failures,
            'error' => $error,
        ]);

        if ($failures >= self::AUTO_PAUSE_THRESHOLD && $this->provider->is_active) {
            $this->provider->updateQuietly(['is_active' => false]);

            Log::critical(__METHOD__.' - Ideasoft connection paused after repeated failures', [
                'provider_id' => $this->provider->id,
                'store' => $this->adapter->getClient()->getStoreDomain(),
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
