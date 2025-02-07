<?php

namespace NextDeveloper\Marketplace\Actions\Products\Imports;

use NextDeveloper\Commons\Actions\AbstractAction;
use NextDeveloper\Commons\Database\Models\Categories;
use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Services\CategoriesService;
use NextDeveloper\Commons\Services\MediaService;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Helpers\HtmlContentHelper;
use NextDeveloper\Marketplace\Helpers\XmlMappingHelper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Exception;
use NextDeveloper\Marketplace\Services\ProductCatalogsService;
use NextDeveloper\Marketplace\Services\ProductsService;
use Publitio\BadJSONResponse;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

/**
 * Import products from Rackdepo provider
 */
class Rackdepo extends AbstractAction
{
    private const IMPORT_STEPS = [
        'FETCH'     => 20,
        'PARSE'     => 40,
        'PROCESS'   => 75,
        'COMPLETE'  => 100,
    ];

    private Client $client;
    private array $xmlMapping;
    

    /**
     * @throws NotAllowedException
     */
    public function __construct(Providers $provider)
    {

        $this->model = $provider;
        $this->initializeComponents();

        parent::__construct();
    }

    /**
     * Handle the import process
     * @throws GuzzleException|Exception|\Throwable
     */
    public function handle(): void
    {
        try {
            $this->setProgress(0, 'Starting Rackdepo product import');

            $xmlString = $this->fetchXmlData();
            $this->setProgress(self::IMPORT_STEPS['FETCH'], 'XML data fetched successfully');
            $data = $this->parseProductData($xmlString);
            $this->setProgress(self::IMPORT_STEPS['PARSE'], 'XML data parsed successfully');

            $this->processImportedData($data);
            $this->setProgress(self::IMPORT_STEPS['COMPLETE'], 'Import completed successfully');

        } catch (\Throwable $e) {
            $this->handleError($e);
            throw $e;
        }
    }

    /**
     * Initialize required components
     */
    private function initializeComponents(): void
    {
        $this->client = new Client();
        $this->xmlMapping = XmlMappingHelper::getRackdepoMapping();
    }

    /**
     * Fetch XML data from the remote URL
     * @throws GuzzleException
     * @throws Exception
     */
    private function fetchXmlData(): string
    {
        if (empty($this->model->url)) {
            throw new Exception('Provider URL is not configured');
        }

        $response = $this->client->get($this->model->url);
        return $response->getBody()->getContents();
    }

    /**
     * Parse the XML data into structured array
     * @throws Exception
     */
    private function parseProductData(string $xmlString): array
    {
        try {
            $xml = $this->createXmlElement($xmlString);
            $items = $this->findItems($xml);

            return array_map(
                fn($item, $index) => $this->processItem($item, $index),
                $items,
                array_keys($items),
            );
        } catch (Exception $e) {
            Log::error('XML Parse Error', [
                'message' => $e->getMessage(),
                'provider_id' => $this->model->id,
            ]);
            throw new Exception('Failed to parse XML data: ' . $e->getMessage());
        }
    }

    /**
     * Process imported data and create products
     * @throws Exception
     */
    private function processImportedData(array $data): void
    {
        $results = [];
        $totalItems = count($data);

        foreach ($data as $index => $item) {
            try {
                $product = $this->createProduct($item);
                $this->createProductCatalogs($product, $item);
                $this->assignCategory($product, $item);
                $this->attachImages($product, $item['images']);

                $results[] = $product;

                $progress = self::IMPORT_STEPS['PROCESS'] +
                    (self::IMPORT_STEPS['COMPLETE'] - self::IMPORT_STEPS['PROCESS']) * ($index / $totalItems);
                $this->setProgress((int)$progress, "Processed {$index} of {$totalItems} products");

            } catch (Exception $e) {
                Log::error('Product Import Error', [
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'provider_id' => $this->model->id,
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue with next item instead of failing entire import
                continue;
            }
        }

        if (empty($results)) {
            throw new Exception('No products were successfully imported');
        }
    }

    /**
     * Create a single product
     * @throws Exception
     */
    private function createProduct(array $item)
    {

        $product = Products::withoutGlobalScopes()
            ->where('name', $item['name'])
            ->where('marketplace_provider_id', $this->model->id)
            ->where('iam_account_id', $this->model->iam_account_id)
            ->first();

        if ($product) {
            ProductsService::update($product->uuid, [
                'name' => $item['name'],
                'description' => $item['description'],
                'content' => $item['content'],
                'tags' => $item['tags'],
                'is_service' => false,
                'is_approved' => false,
                'marketplace_provider_id' => $this->model->id,
            ]);

            return $product;
        }


        return ProductsService::create([
            'name' => $item['name'],
            'description' => $item['description'],
            'content' => $item['content'],
            'tags' => $item['tags'],
            'iam_account_id' => $this->model->iam_account_id,
            'iam_user_id' => $this->model->iam_user_id,
            'marketplace_market_id' => $this->model->marketplace_market_id,
            'is_service' => false,
            'is_approved' => false,
            'marketplace_provider_id' => $this->model->id,
        ]);
    }

    /**
     * Create product catalogs for a product
     * @throws Exception
     */
    private function createProductCatalogs($product, array $item): void
    {
        foreach ($item['pricing'] as $pricing) {
            $existing = ProductCatalogs::withoutGlobalScopes()
                ->where('sku', $item['sku'])
                ->where('iam_account_id', $this->model->iam_account_id)
                ->first();

            $data = [
                'name' => $pricing['name'],
                'price' => $pricing['price'],
                'marketplace_product_id' => $product->id,
                'sku' => $item['sku'],
                'quantity_in_inventory' => $pricing['quantity'],
                'trial_date' => 0,
                'iam_account_id' => $this->model->iam_account_id,
                'iam_user_id' => $this->model->iam_user_id,
                'args' => [
                    'tax' => $pricing['tax'],
                    'market_price' => $pricing['market_price'],
                    'price_with_tax' => $pricing['price_with_tax'],
                    'currency' => $pricing['currency'],
                    'warranty' => $item['warranty'],
                ],
            ];

            if ($existing) {
                ProductCatalogsService::update($existing->uuid, $data);
                continue;
            }

            ProductCatalogsService::create($data);
        }
    }

    /**
     * Assign category to a product
     * @throws Exception
     */
    private function assignCategory($product, array $item): void
    {
        if (empty($item['category']['name'])) {
            return;
        }

        $category = Categories::where('name', $item['category']['name'])
                    ->first();

        if (!$category) {
            $category = CategoriesService::create([
                'name' => $item['category']['name'],
            ]);
        }

        $product->updateQuietly(['common_category_id' => $category->id]);
    }

    /**
     * Attach images to a product
     * @throws BadJSONResponse
     */
    private function attachImages($product, array $images): void
    {
        foreach ($images as $index => $image) {
            $data = [
                'file'           => $image,
                'object_id'      => $product->uuid,
                'object_type'    => 'NextDeveloper\\Marketplace\\Database\\Models\\Products',
            ];

            $existing = Media::withoutGlobalScopes()
                ->where('object_id', $product->id)
                ->where('object_type', 'NextDeveloper\\Marketplace\\Database\\Models\\Products')
                ->where('file_name', basename($image))
                ->first();

            if (!$existing) {
                MediaService::create($data);
            }
        }
    }

    /**
     * Handle various types of errors
     */
    private function handleError(\Throwable $e): void
    {
        $context = [
            'provider_id' => $this->model->id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($e instanceof \TypeError) {
            Log::error('Type Error in Import Product', $context);
        } elseif ($e instanceof GuzzleException) {
            Log::error('API Connection Error', $context);
        } else {
            Log::error('General Import Error', $context);
        }
    }

    /**
     * Create SimpleXMLElement from string
     * @throws Exception
     */
    private function createXmlElement(string $xmlString): SimpleXMLElement
    {
        if (!preg_match('/^<\?xml/', $xmlString)) {
            $xmlString = '<?xml version="1.0" encoding="UTF-8"?>' . $xmlString;
        }

        return new SimpleXMLElement($xmlString, LIBXML_NOCDATA);
    }

    /**
     * Find all items in the XML using xpath
     * @throws Exception
     */
    private function findItems(SimpleXMLElement $xml): array
    {
        $itemNode = $this->xmlMapping['item_node'];
        $items = $xml->xpath("//{$itemNode}");

        if (empty($items)) {
            throw new Exception("Invalid XML structure. No {$itemNode} nodes found.");
        }

        return $items;
    }

    /**
     * Process a single item from the XML
     */
    private function processItem(SimpleXMLElement $item, int $index): array
    {
        $result = [];

        // Map basic info
        $result['name'] = $this->parseField($item, $this->xmlMapping['fields']['basic_info']['label']);
        $result['sku'] = $this->parseField($item, $this->xmlMapping['fields']['basic_info']['stock_code']);
        $result['tags'] = explode(',', $this->parseField($item, $this->xmlMapping['fields']['basic_info']['search_keywords']) ?? '');

        // Map category info
        $result['category'] = [
            'main' => $this->parseField($item, $this->xmlMapping['fields']['category']['main_category']['name']),
            'main_id' => $this->parseField($item, $this->xmlMapping['fields']['category']['main_category']['id']),
            'sub' => $this->parseField($item, $this->xmlMapping['fields']['category']['sub_category']['name']),
            'sub_id' => $this->parseField($item, $this->xmlMapping['fields']['category']['sub_category']['id']),
        ];

        // Map pricing info
        $result['pricing'] = [
            [
                'name' => 'Default Price',
                'price' => $this->parseField($item, $this->xmlMapping['fields']['pricing']['price1']),
                'market_price' => $this->parseField($item, $this->xmlMapping['fields']['pricing']['market_price']),
                'currency' => $this->parseField($item, $this->xmlMapping['fields']['pricing']['currency']),
                'tax' => $this->parseField($item, $this->xmlMapping['fields']['pricing']['tax']),
                'price_with_tax' => $this->parseField($item, $this->xmlMapping['fields']['pricing']['price_with_tax']),
                'quantity' => $this->parseField($item, $this->xmlMapping['fields']['stock']['amount']),
            ],
        ];

        // Map warranty
        $result['warranty'] = $this->parseField($item, $this->xmlMapping['fields']['warranty']);

        // Map details and full details directly to description and content
        $detailsNodes = $item->xpath('details');
        $fullDetailsNodes = $item->xpath('fullDetails');

        $result['description'] = !empty($detailsNodes) ?
            HtmlContentHelper::cleanContent((string)$detailsNodes[0]) :
            $this->parseField($item, $this->xmlMapping['fields']['basic_info']['label']);

        $result['content'] = !empty($fullDetailsNodes) ?
            HtmlContentHelper::cleanContent((string)$fullDetailsNodes[0]) :
            $result['description'];

        // Map images
        $result['images'] = $this->extractImages($item);

        return $result;
    }

    private function getDetailedContent(SimpleXMLElement $item): string
    {
        $content = [];

        // Add basic info
        $content[] = "# " . $this->parseField($item, $this->xmlMapping['fields']['basic_info']['label']);
        $content[] = "\n## Product Details";
        $content[] = "Brand: " . $this->parseField($item, $this->xmlMapping['fields']['basic_info']['brand']);

        // Add stock info
        $content[] = "\n## Stock Information";
        $content[] = "Stock Type: " . $this->parseField($item, $this->xmlMapping['fields']['stock']['type']);
        $content[] = "Stock Status: " . $this->parseField($item, $this->xmlMapping['fields']['stock']['status']);

        return implode("\n", $content);
    }

    private function extractImages(SimpleXMLElement $item): array
    {
        $images = [];
        foreach ($this->xmlMapping['fields']['images'] as $key => $config) {
            $image = $this->parseField($item, $config);
            if ($image) {
                $images[] = $image;
            }
        }
        return $images;
    }

    /**
     * Parse a single field from the XML
     */
    private function parseField(SimpleXMLElement $item, array $config): ?string
    {
        try {
            $nodes = $item->xpath($config['path']);

            if (!empty($nodes) && $nodes[0] instanceof SimpleXMLElement) {
                $value = (string)$nodes[0];
                return $this->castValue($value, $config['type']);
            }
        } catch (Exception $e) {
            $this->logError($e);
        }

        return null;
    }

    /**
     * Cast value to specified type
     */
    private function castValue(?string $value, string $type)
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = HtmlContentHelper::cleanContent($value);

        switch ($type) {
            case 'int':
                return is_numeric($value) ? (int)$value : null;
            case 'float':
                return is_numeric($value) ? (float)$value : null;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            default:
                return $value;
        }
    }

    /**
     * Log error with context
     */
    private function logError(\Throwable $e): void
    {
        \Log::error('Field Parse Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
