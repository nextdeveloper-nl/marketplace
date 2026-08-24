<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Base class for the per-entity Shopify pull jobs.
 *
 * One job instance = one provider × one entity type. The job owns the cursor
 * discipline: it reads its starting point from marketplace_provider_sync_states
 * through ShopifyService::cursorFor(), and only advances the cursor on success
 * via markSynced(). A failed run leaves the cursor untouched, so the next run
 * re-scans the same window instead of silently losing it — the CLI --date
 * pattern this replaces lost a window every time a run was missed.
 *
 * Every write in a sync job is quiet (saveQuietly / updateQuietly) on purpose:
 * that is layer 2 of the echo-loop guard — inbound applies must never fire
 * observers that could trigger an outbound push. Layer 1 (the sync_hash
 * fingerprint) lives with each entity job.
 */
abstract class AbstractShopifySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Backfills paginate the entire catalog; give them room.
     */
    public int $timeout = 900;

    public int $tries = 1;

    /**
     * Filled by handle(); read by the console command on inline runs so it can
     * render a per-provider summary without re-querying anything.
     *
     * @var array<string, mixed>
     */
    public array $report = [];

    public function __construct(
        public int $providerId,
        public bool $dryRun = false,
        public bool $full = false
    ) {
        $this->onQueue(self::QUEUE_NAME);
    }

    /**
     * The entity_type key in marketplace_provider_sync_states.
     */
    abstract protected function entityType(): string;

    /**
     * Pull and apply one entity's delta.
     *
     * @return array{processed: int, high_watermark: Carbon|null, details: array<string, mixed>}
     */
    abstract protected function sync(ShopifyService $service, Carbon $since): array;

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)
            ->find($this->providerId);

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'shopify') {
            Log::warning(static::class.' - provider missing or not a Shopify provider', [
                'provider_id' => $this->providerId,
            ]);

            $this->report = ['provider_id' => $this->providerId, 'skipped' => 'not a Shopify provider'];

            return;
        }

        if (! $provider->is_active) {
            $this->report = ['provider_id' => $provider->id, 'skipped' => 'provider is paused (is_active=false)'];

            return;
        }

        /*
         * Run as the provider's own user/account (the FetchProviderOrdersCommand
         * convention) with the observer can() checks bypassed for the duration.
         * The bypass is required: sync touches models across accounts the
         * acting user is not a member of, exactly the situation leo4's
         * RoleBypassHelper exists for. The previous value is restored through
         * reflection because bypassRolesCheck(false) is a documented no-op.
         */
        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);

        $previousBypass = UserHelper::bypassRolesCheck();
        UserHelper::bypassRolesCheck(true);

        try {
            $service = new ShopifyService($provider);
            $entity = $this->entityType();

            $since = $this->full
                ? Carbon::create(2000, 1, 1)
                : $service->cursorFor($entity);

            try {
                $result = $this->sync($service, $since);

                if (! $this->dryRun) {
                    $service->markSynced($entity, $result['high_watermark'], $result['processed']);
                }

                $this->report = [
                    'provider_id' => $provider->id,
                    'entity' => $entity,
                    'since' => $since->toIso8601String(),
                    'processed' => $result['processed'],
                    'high_watermark' => $result['high_watermark']?->toIso8601String(),
                    'dry_run' => $this->dryRun,
                    'details' => $result['details'],
                ];

                Log::info(static::class.' - sync completed', $this->report);
            } catch (\Throwable $e) {
                if (! $this->dryRun) {
                    $service->markFailed($entity, $e->getMessage());
                }

                $this->report = [
                    'provider_id' => $provider->id,
                    'entity' => $entity,
                    'since' => $since->toIso8601String(),
                    'dry_run' => $this->dryRun,
                    'error' => $e->getMessage(),
                ];

                throw $e;
            }
        } finally {
            $property = new \ReflectionProperty(UserHelper::class, 'isBypassRolesCheck');
            $property->setAccessible(true);
            $property->setValue(null, $previousBypass);
        }
    }

    /**
     * Whether the provider's sync_policy allows pulling this entity.
     *
     * Missing policy means the plan's v1 defaults, which pull everything.
     */
    protected function policyAllowsPull(Providers $provider, string $entity): bool
    {
        $direction = data_get($provider->getApiConfigArray(), 'sync_policy.'.$entity.'.direction');

        return $direction === null || $direction !== 'push';
    }

    protected function maxWatermark(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate->greaterThan($current)) {
            return $candidate;
        }

        return $current;
    }
}
