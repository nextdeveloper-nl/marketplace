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
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayAdapter;

/**
 * Orchestrates one eBay seller connection.
 *
 * Mirrors IdeasoftService: constructed with a Providers row (adapter = 'ebay'), owns the adapter,
 * and is the object the console commands and queue jobs talk to. Sync state (cursor, health,
 * failure count) lives in marketplace_provider_sync_states per entity.
 *
 * Unlike Ideasoft/Shopify, eBay has no "modified since" filter, so the sync-state cursor is not a
 * delta watermark: a later phase walks the inventory in full and only uses markSynced() to record
 * the run and its health.
 */
class EbayService
{
    /**
     * Consecutive failures before the connection is paused and the merchant told.
     * Matches IdeasoftService / ShopifyService.
     */
    public const AUTO_PAUSE_THRESHOLD = 5;

    private EbayAdapter $adapter;

    private ?Markets $market = null;

    private ?EbayApplyService $applier = null;

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
        $this->adapter = new EbayAdapter($this->provider);
    }

    public function getAdapter(): EbayAdapter
    {
        return $this->adapter;
    }

    /**
     * Writes normalized eBay records into the local model graph. Shared by every sync job so they
     * apply records by exactly the same rules.
     */
    public function applier(): EbayApplyService
    {
        return $this->applier ??= new EbayApplyService($this->provider, $this->market?->common_currency_id);
    }

    public function getMarket(): ?Markets
    {
        return $this->market;
    }

    /**
     * Live check against eBay: true when the stored seller token still identifies a user.
     */
    public function authenticate(): bool
    {
        return $this->adapter->authenticate([]);
    }

    /**
     * Orders are not part of the eBay integration. Present because
     * FetchProviderOrdersCommand calls fetchOrders() on every registered adapter service.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrders(Carbon $since): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Sync state
    // -------------------------------------------------------------------------

    /**
     * The sync-state row for one entity, created on first use.
     *
     * Reads drop AuthorizationScope: this is meant to run on a queue with no user context, and
     * the row belongs to the provider rather than to whoever triggered it.
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
     * Where an interrupted full scan of this entity should resume (0 = start from the beginning).
     * eBay has no delta, so a scan walks the whole inventory; the page offset is kept in
     * cursor_token so a failure part-way does not restart a long scan from zero.
     */
    public function scanOffset(string $entityType): int
    {
        return (int) $this->syncState($entityType)->cursor_token;
    }

    /**
     * Remember the next page offset of a running scan, or clear it (null) when the scan finished.
     */
    public function saveScanOffset(string $entityType, ?int $offset): void
    {
        $this->syncState($entityType)->updateQuietly(['cursor_token' => $offset === null ? null : (string) $offset]);
    }

    /**
     * Record a successful run. The cursor only ever moves forward.
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

        Log::error(__METHOD__.' - eBay sync failed', [
            'provider_id' => $this->provider->id,
            'entity' => $entityType,
            'consecutive_failures' => $failures,
            'error' => $error,
        ]);

        if ($failures >= self::AUTO_PAUSE_THRESHOLD && $this->provider->is_active) {
            $this->provider->updateQuietly(['is_active' => false]);

            Log::critical(__METHOD__.' - eBay connection paused after repeated failures', [
                'provider_id' => $this->provider->id,
                'marketplace_id' => $this->adapter->getClient()->getMarketplaceId(),
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
