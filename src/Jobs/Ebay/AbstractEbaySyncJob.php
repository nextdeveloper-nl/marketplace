<?php

namespace NextDeveloper\Marketplace\Jobs\Ebay;

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
use NextDeveloper\Marketplace\Services\Marketplaces\EbayService;

/**
 * Base class for the per-entity eBay pull jobs (mirrors AbstractIdeasoftSyncJob).
 *
 * One job instance = one provider x one entity. eBay has no "modified since" filter, so a run is a
 * full scan of the seller's inventory rather than a delta: the scan position is kept in the sync
 * state's cursor_token so a failed run resumes where it stopped, and the apply layer skips
 * records whose content hash did not change. A finished scan clears the position and stamps the
 * scan start as the sync state's cursor.
 */
abstract class AbstractEbaySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    public int $timeout = 1800;

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
     * Scan and apply one entity.
     *
     * @param  bool  $resume  True when an interrupted scan may be continued from its saved offset.
     * @return array{processed: int, details: array<string, mixed>}
     */
    abstract protected function sync(EbayService $service, bool $resume): array;

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->find($this->providerId);

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'ebay') {
            Log::warning(static::class.' - provider missing or not an eBay provider', [
                'provider_id' => $this->providerId,
            ]);

            $this->report = ['provider_id' => $this->providerId, 'skipped' => 'not an eBay provider'];

            return;
        }

        if (! $provider->is_active) {
            $this->report = ['provider_id' => $provider->id, 'skipped' => 'provider is paused (is_active=false)'];

            return;
        }

        if ((string) $provider->getDecryptedAccessToken() === '') {
            $this->report = ['provider_id' => $provider->id, 'skipped' => 'eBay account is not connected (no access token)'];

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
            $service = new EbayService($provider);
            $entity = $this->entityType();
            $startedAt = Carbon::now();

            // --full restarts the scan; a dry run never reads or writes the saved position.
            if ($this->full && ! $this->dryRun) {
                $service->saveScanOffset($entity, null);
            }
            $resume = ! $this->full && ! $this->dryRun;

            try {
                $result = $this->sync($service, $resume);

                if (! $this->dryRun) {
                    $service->markSynced($entity, $startedAt, $result['processed']);
                }

                $this->report = [
                    'provider_id' => $provider->id,
                    'entity' => $entity,
                    'since' => $startedAt->toIso8601String(),
                    'processed' => $result['processed'],
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
                    'since' => $startedAt->toIso8601String(),
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
}
