<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\TrendyolGoYemekService;

/**
 * Command to fetch orders from marketplace providers
 * 
 * This command fetches orders from all active marketplace providers
 * using their respective service classes.
 */
class FetchProviderOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nextdeveloper:fetch-provider-orders 
                            {--date= : Date to fetch orders from (defaults to today)}
                            {--provider= : Specific provider ID to fetch orders from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch orders from marketplace providers';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $date = $this->option('date');
        $providerId = $this->option('provider');

        // Parse and validate date
        if ($date) {
            try {
                $date = Carbon::parse($date);
            } catch (Exception $e) {
                $this->error('Invalid date format. Please use a valid date.');
                return;
            }
        } else {
            $date = now();
        }

        // Get providers query
        $providersQuery = Providers::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('adapter');

        // Filter by provider ID if specified
        if ($providerId) {
            $providersQuery->where('id', $providerId);
        }

        // Get providers
        $providers = $providersQuery->get();

        if ($providers->isEmpty()) {
            $this->info('No active providers found.');
            return;
        }

        $this->info("Fetching orders for " . $providers->count() . " provider(s) from " . $date->format('Y-m-d'));

        // Create a progress bar
        $bar = $this->output->createProgressBar($providers->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($providers as $provider) {

            // Set user and account context
            UserHelper::setUserById($provider->iam_user_id);
            UserHelper::setCurrentAccountById($provider->iam_account_id);

            // Process each provider
            $result = $this->processProvider($provider, $date);
            $result ? $successCount++ : $failCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Order fetching completed:");
        $this->info("- Successful providers: $successCount");
        $this->info("- Failed providers: $failCount");
    }

    /**
     * Process a single provider
     *
     * @param Providers $provider Provider model
     * @param Carbon $date Date to fetch orders from
     * @return bool Success status
     */
    private function processProvider(Providers $provider, Carbon $date): bool
    {
        $this->newLine();
        $this->line("Processing provider: {$provider->name} ({$provider->adapter})");

        try {
            // Get service class from configuration if available
            $serviceClass = $this->getServiceClassForProvider($provider);

            if (!$serviceClass) {
                $this->warn("No service class configured for provider adapter: {$provider->adapter}");
                return false;
            }

            if (!class_exists($serviceClass)) {
                $this->warn("Service class not found: {$serviceClass}");
                return false;
            }

            // Create a service instance
            $service = new $serviceClass($provider);

            // Fetch orders
            $service->fetchOrders($date);

            $this->info("Orders fetched successfully for provider: {$provider->name}");
            return true;
        } catch (Exception $e) {
            $this->error("Error fetching orders for provider {$provider->name}: " . $e->getMessage());
            Log::error(__METHOD__ . " - Error fetching orders for provider {$provider->name}: " . $e->getMessage(), [
                'provider_id' => $provider->id,
                'date' => $date->format('Y-m-d H:i:s'),
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * Get the service class for a provider
     *
     * @param Providers $provider Provider model
     * @return string|null Service class name or null if not found
     */
    private function getServiceClassForProvider(Providers $provider): ?string
    {
        // Map of adapter names to service classes
        $adapterServiceMap = [
            'TrendyolGoYemek' => TrendyolGoYemekService::class,
        ];

        // Check if the adapter is in the map
        if (isset($adapterServiceMap[$provider->adapter])) {
            return $adapterServiceMap[$provider->adapter];
        }

        return null;
    }
}
