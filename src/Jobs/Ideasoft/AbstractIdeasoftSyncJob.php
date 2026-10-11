<?php

namespace NextDeveloper\Marketplace\Jobs\Ideasoft;

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
use NextDeveloper\Marketplace\Services\Marketplaces\IdeasoftService;

/**
 * Base class for the per-entity Ideasoft pull jobs (mirrors AbstractShopifySyncJob).
 *
 * One job instance = one provider x one entity. The cursor lives in
 * marketplace_provider_sync_states and only advances on success, so a failed run is retried over
 * the same window on the next schedule.
 */
abstract class AbstractIdeasoftSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * Filled by handle(); read by the console command on inline runs.
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

    /** The entity_type key in marketplace_provider_sync_states. */
    abstract protected function entityType(): string;

    /**
     * Pull and apply one entity's delta.
     *
     * @return array{processed: int, high_watermark: Carbon|null, details: array<string, mixed>}
     */
    abstract protected function sync(IdeasoftService $service, Carbon $since): array;

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->find($this->providerId);

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'ideasoft') {
            Log::warning(static::class.' - provider missing or not an Ideasoft provider', [
                'provider_id' => $this->providerId,
            ]);

            $this->report = ['provider_id' => $this->providerId, 'skipped' => 'not an Ideasoft provider'];

            return;
        }

        if (! $provider->is_active) {
            $this->report = ['provider_id' => $provider->id, 'skipped' => 'provider is paused (is_active=false)'];

            return;
        }

        if ((string) $provider->getDecryptedAccessToken() === '') {
            $this->report = ['provider_id' => $provider->id, 'skipped' => 'store is not connected (no access token)'];

            return;
        }

        // Run as the provider's own user/account with the observer can() checks bypassed for the
        // duration (sync writes records the acting user has no role for). The previous value is
        // restored through reflection because bypassRolesCheck(false) is a documented no-op.
        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);

        $previousBypass = UserHelper::bypassRolesCheck();
        UserHelper::bypassRolesCheck(true);

        try {
            $service = new IdeasoftService($provider);
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

    protected function maxWatermark(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null || $candidate->greaterThan($current) ? $candidate : $current;
    }
}
