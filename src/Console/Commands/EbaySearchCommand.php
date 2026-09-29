<?php

namespace NextDeveloper\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayBrowseAdapter;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay\EbayClient;

/**
 * Keyword search over the public eBay catalogue. Read-only: nothing is stored.
 */
class EbaySearchCommand extends Command
{
    protected $signature = 'marketplace:ebay-search
                            {query : Keywords to search for}
                            {--limit=20 : Results per page (max 200)}
                            {--offset=0 : Result offset for paging}
                            {--marketplace= : eBay marketplace id, e.g. EBAY_US, EBAY_DE (default from config)}
                            {--filter= : eBay filter expression, e.g. "price:[100..300],priceCurrency:USD"}
                            {--sort= : price, -price, newlyListed or endingSoonest}
                            {--category= : Restrict to eBay category ids (comma separated)}
                            {--json : Emit the normalized result as JSON}';

    protected $description = 'Search eBay listings by keyword (Browse API)';

    public function handle(): int
    {
        // eBay silently ignores an unknown sort, which would look like a working sort.
        $sort = $this->option('sort');

        if ($sort !== null && ! in_array($sort, ['price', '-price', 'newlyListed', 'endingSoonest'], true)) {
            $this->error('Unsupported --sort "'.$sort.'". Use price, -price, newlyListed or endingSoonest.');

            return self::FAILURE;
        }

        try {
            $adapter = new EbayBrowseAdapter(new EbayClient($this->option('marketplace') ?: null));

            $result = $adapter->search($this->argument('query'), [
                'limit' => $this->option('limit'),
                'offset' => $this->option('offset'),
                'filter' => $this->option('filter'),
                'sort' => $this->option('sort'),
                'category_ids' => $this->option('category'),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s: %d total matches, showing %d from offset %d',
            $result['marketplace_id'],
            $result['total'],
            count($result['items']),
            $result['offset']
        ));

        $this->table(
            ['Item id', 'Title', 'Price', 'Condition', 'Seller', 'URL'],
            array_map(fn (array $item) => [
                $item['external_id'],
                Str::limit((string) $item['title'], 60),
                $item['price'] !== null ? number_format($item['price'], 2).' '.$item['currency'] : '-',
                $item['condition'] ?? '-',
                $item['seller']['username'] ?? '-',
                $item['item_url'],
            ], $result['items'])
        );

        return self::SUCCESS;
    }
}
