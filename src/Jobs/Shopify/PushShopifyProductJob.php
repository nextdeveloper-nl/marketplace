<?php

namespace NextDeveloper\Marketplace\Jobs\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\Providers;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyProductPushService;
use NextDeveloper\Marketplace\Services\Marketplaces\ShopifyService;

/**
 * Pushes one locally-edited product to every Shopify shop it is mapped to, as
 * soon as the edit happens.
 *
 * Bound (see leo:bind-events) to:
 *   updated:NextDeveloper\Marketplace\Products
 *   updated:NextDeveloper\Marketplace\ProductCatalogs
 *
 * so a panel or API save reaches Shopify in seconds instead of waiting for the
 * scheduled sweep. PushShopifyProductsJob still runs on its schedule and stays
 * the safety net for edits made while a shop was unreachable.
 *
 * Product fields and variant prices are written here; stock is handed to
 * PushShopifyInventoryJob for this one product, because quantities need the
 * compare-and-swap that job owns.
 *
 * The gates are the bulk job's gates: active Shopify provider, products
 * direction allows push, and catalogue push approved after a reviewed dry-run.
 * The bulk job's change-ratio guard is deliberately absent — this job is scoped
 * to the single product a human just saved, so "most of the catalogue changed"
 * cannot describe it.
 *
 * Echo safety: everything the pull and the webhook processor write locally is
 * written quietly, which fires no observer and therefore no event, so a change
 * that arrived FROM Shopify never reaches this job. The diff() call is the
 * second net — an identical product is a no-op.
 *
 * No user context is set here on purpose. The bulk job may switch identity
 * freely because it runs in a worker, but this one is dispatched from inside
 * the request that saved the product, and on the sync queue driver it would
 * execute there too; reassigning the current user mid-request would leak the
 * provider's identity into the rest of that request. Every read below drops the
 * authorization scope instead, and every write is quiet.
 */
class PushShopifyProductJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'marketplace-sync';

    /**
     * Saving a product in the panel updates the product and then its catalogue
     * rows, which is several events for one human action. Holding the job back
     * briefly collapses them into a single push.
     */
    public const COALESCE_SECONDS = 10;

    public int $timeout = 300;

    public int $tries = 1;

    public ?int $productId;

    /** @var array<string, mixed> */
    public array $report = [];

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(Model $model, public array $params = [])
    {
        $this->productId = $this->resolveProductId($model);
        $this->queue = self::QUEUE_NAME;
        $this->delay = Carbon::now()->addSeconds(self::COALESCE_SECONDS);
    }

    /**
     * One pending push per product: a second save while one is still queued is
     * dropped, and a save during processing queues a fresh one.
     */
    public function uniqueId(): string
    {
        return 'shopify-product-push:'.$this->productId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(): void
    {
        if ($this->productId === null) {
            return;
        }

        $product = Products::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)->find($this->productId);

        if (! $product) {
            return;
        }

        $mappings = ProductMappings::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $product->id)
            ->get();

        foreach ($mappings as $mapping) {
            $provider = Providers::withoutGlobalScope(AuthorizationScope::class)
                ->withoutGlobalScope(LimitScope::class)->find($mapping->marketplace_provider_id);

            if (! $this->isPushable($provider)) {
                continue;
            }

            $this->pushTo($provider, $product, $mapping);
        }
    }

    /**
     * The same three gates the bulk push stands behind, asked of one shop.
     */
    private function isPushable(?Providers $provider): bool
    {
        if (! $provider || ! $provider->is_active) {
            return false;
        }

        if (strtolower(trim((string) $provider->adapter)) !== 'shopify') {
            return false;
        }

        if (! $provider->canPush('products')) {
            return false;
        }

        return data_get($provider->getApiConfigArray(), 'product_push.approved_at') !== null;
    }

    private function pushTo(Providers $provider, Products $product, ProductMappings $mapping): void
    {
        try {
            $pusher = new ShopifyProductPushService(new ShopifyService($provider));

            $diff = $pusher->diff($product);

            if ($diff !== null) {
                $pusher->push($product, $mapping);
            }

            $variants = $pusher->pushVariants($product);

            /*
             * Settle the row last. Pushing re-reads the product, which rewrites
             * the local rows and so moves their updated_at; this has to land
             * after that or the scheduled sweep still sees a local edit.
             */
            $pusher->markReconciled($mapping);

            // Stock keeps its own compare-and-swap path — overselling is what
            // blind quantity writes cause — so it is handed to the job that
            // owns that, narrowed to this product.
            $inventory = false;

            if ($provider->canPush('inventory')) {
                PushShopifyInventoryJob::dispatch($provider->id, false, (int) $product->id);
                $inventory = true;
            }

            $this->report[$provider->id] = [
                'product' => $diff !== null ? 'pushed' : 'already_matching',
                'variants_pushed' => $variants,
                'inventory_queued' => $inventory,
            ];

            if ($diff !== null || $variants > 0) {
                Log::info(__METHOD__.' - product pushed on save', [
                    'provider_id' => $provider->id,
                    'product_id' => $product->id,
                    'external_id' => $mapping->external_product_id,
                    'variants_pushed' => $variants,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error(__METHOD__.' - immediate product push failed', [
                'provider_id' => $provider->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The bound events carry either the product or one of its catalogue rows;
     * both mean "this product changed here".
     */
    private function resolveProductId(Model $model): ?int
    {
        if ($model instanceof Products) {
            return (int) $model->id;
        }

        if ($model instanceof ProductCatalogs) {
            return $model->marketplace_product_id ? (int) $model->marketplace_product_id : null;
        }

        return null;
    }
}
