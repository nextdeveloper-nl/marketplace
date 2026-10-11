<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ideasoft\IdeasoftAdapter;

/**
 * Prints a few raw and normalized records from a connected Ideasoft store.
 *
 * Ideasoft's API payloads are not publicly documented, so the field mapping in IdeasoftAdapter is
 * a best guess. Run this against a real store to see the actual payload and fix the mapping.
 * Read-only: it never writes to Ideasoft or to our tables.
 */
class IdeasoftProbeCommand extends Command
{
    protected $signature = 'marketplace:ideasoft-probe
                            {provider : Provider id or uuid}
                            {--entity=products : products | inventory}
                            {--limit=3 : How many records to print}';

    protected $description = 'Show sample raw and normalized records from a connected Ideasoft store';

    public function handle(): int
    {
        $ref = $this->argument('provider');

        $provider = Providers::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where(fn ($q) => is_numeric($ref) ? $q->where('id', $ref) : $q->where('uuid', $ref))
            ->first();

        if (! $provider || strtolower(trim((string) $provider->adapter)) !== 'ideasoft') {
            $this->error('No Ideasoft provider found for '.$ref);

            return self::FAILURE;
        }

        $adapter = new IdeasoftAdapter($provider);
        $limit = max(1, (int) $this->option('limit'));
        $epoch = Carbon::createFromTimestamp(0);

        if ($this->option('entity') === 'inventory') {
            $rows = array_slice($adapter->fetchInventoryLevels($epoch), 0, $limit);
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $raw = array_slice($adapter->fetchProducts($epoch), 0, $limit);

        foreach ($raw as $i => $product) {
            $this->info('--- product '.($i + 1).' raw');
            $this->line(json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('--- product '.($i + 1).' normalized');
            $normalized = $adapter->normalizeProductData($product);
            unset($normalized['raw']);
            $this->line(json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->info(count($raw).' product(s) shown.');

        return self::SUCCESS;
    }
}
