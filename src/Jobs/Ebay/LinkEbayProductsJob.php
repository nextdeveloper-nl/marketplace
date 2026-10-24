<?php

namespace NextDeveloper\Marketplace\Jobs\Ebay;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\EbayLinkService;

/**
 * Links every unlinked local product of an eBay provider's market to eBay, one product at a time,
 * and keeps a progress report in the cache for the panel (see report()).
 *
 * Mode match_by_sku links products whose catalog SKUs already exist on eBay. Mode create_listings
 * additionally creates the listing for products whose SKU is not on eBay; that starts LIVE listings,
 * so a real (non dry-run) run must be given a hard $limit on how many it may create, and a product
 * that cannot be listed with certainty (no image, no category, missing aspects, ...) is reported as
 * blocked rather than guessed.
 *
 * One product failing never stops the run; it is reported and the next one is tried.
 */
class LinkEbayProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    public const MODE_MATCH = 'match_by_sku';

    public const MODE_CREATE = 'create_listings';

    /** Per-product lines kept in the report; the counts always cover every product. */
    private const MAX_REPORT_ITEMS = 500;

    private const REPORT_TTL_HOURS = 24;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $providerId,
        public string $mode = self::MODE_MATCH,
        public bool $dryRun = false,
        public ?int $limit = null,
    ) {
        $this->onQueue(self::QUEUE_NAME);
    }

    /**
     * The latest report for a provider, or null when none ran in the last day.
     *
     * @return array<string, mixed>|null
     */
    public static function report(int $providerId): ?array
    {
        return Cache::get(self::cacheKey($providerId));
    }

    public static function cacheKey(int $providerId): string
    {
        return 'marketplace:ebay:link-report:'.$providerId;
    }

    /** Seed the report so the panel sees "queued" before a worker picks the job up. */
    public static function markQueued(int $providerId, string $mode, bool $dryRun, ?int $limit): void
    {
        Cache::put(self::cacheKey($providerId), [
            'status' => 'queued', 'mode' => $mode, 'dry_run' => $dryRun, 'limit' => $limit,
            'queued_at' => Carbon::now()->toIso8601String(),
            'counts' => [], 'items' => [],
        ], now()->addHours(self::REPORT_TTL_HOURS));
    }

    public function handle(): void
    {
        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)->find($this->providerId);

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'ebay' || ! $provider->is_active
            || (string) $provider->getDecryptedAccessToken() === '') {
            $this->save(['status' => 'failed', 'error' => 'The eBay provider is missing, paused or not connected.', 'counts' => [], 'items' => []]);

            return;
        }

        // Same identity handling as the sync jobs: the provider's own user/account, raw state restored after.
        $rawUser = new \ReflectionProperty(UserHelper::class, 'user');
        $rawAccount = new \ReflectionProperty(UserHelper::class, 'account');
        $previousUser = $rawUser->getValue();
        $previousAccount = $rawAccount->getValue();

        UserHelper::setUserById($provider->iam_user_id);
        UserHelper::setCurrentAccountById($provider->iam_account_id);

        try {
            UserHelper::withRolesCheckBypassed(fn () => $this->run($provider));
        } finally {
            $rawUser->setValue(null, $previousUser);
            $rawAccount->setValue(null, $previousAccount);
        }
    }

    private function run(Providers $provider): void
    {
        $report = [
            'status' => 'running', 'mode' => $this->mode, 'dry_run' => $this->dryRun, 'limit' => $this->limit,
            'started_at' => Carbon::now()->toIso8601String(), 'counts' => [], 'items' => [],
        ];
        $created = 0;
        $seen = 0;
        $this->save($report);

        // Products of this market and account that have no mapping to this provider yet.
        $query = Products::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_market_id', $provider->marketplace_market_id)
            ->where('iam_account_id', $provider->iam_account_id)
            ->whereNotIn('id', ProductMappings::withoutGlobalScope(AuthorizationScope::class)->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $provider->id)->select('marketplace_product_id'))
            ->orderBy('id');

        try {
            $query->chunkById(50, function ($products) use ($provider, &$report, &$created, &$seen) {
                foreach ($products as $product) {
                    // A hard stop on live listings created in one run.
                    $createAllowed = $this->mode === self::MODE_CREATE && ($this->dryRun || $created < (int) $this->limit);

                    try {
                        $result = EbayLinkService::forProduct($product, $provider->uuid)->link([
                            'create_listing' => $createAllowed,
                            'dry_run' => $this->dryRun,
                        ]);
                        $status = $result['status'];
                        $message = $result['message'];
                    } catch (\Throwable $e) {
                        $status = 'failed';
                        $message = $e->getMessage();
                        Log::warning(static::class.' - product could not be linked', ['product_id' => $product->id, 'error' => $message]);
                    }

                    if ($status === 'listing_created') {
                        $created++;
                    }

                    $report['counts'][$status] = ($report['counts'][$status] ?? 0) + 1;

                    if (count($report['items']) < self::MAX_REPORT_ITEMS) {
                        $report['items'][] = ['product_id' => $product->uuid, 'name' => $product->name, 'status' => $status, 'message' => mb_substr((string) $message, 0, 300)];
                    }

                    // Progress for the panel without a cache write per product.
                    if (++$seen % 10 === 0) {
                        $this->save($report);
                    }
                }
            });

            $report['status'] = 'done';
        } catch (\Throwable $e) {
            $report['status'] = 'failed';
            $report['error'] = $e->getMessage();
            Log::error(static::class.' - link run failed', ['provider_id' => $provider->id, 'error' => $e->getMessage()]);
        }

        $report['finished_at'] = Carbon::now()->toIso8601String();
        $report['processed'] = $seen;
        $this->save($report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function save(array $report): void
    {
        Cache::put(self::cacheKey($this->providerId), $report, now()->addHours(self::REPORT_TTL_HOURS));
    }
}
