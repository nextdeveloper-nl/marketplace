<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogMappings;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify\ShopifyGraphQL;

/**
 * Writes one already-authorised product to Shopify.
 *
 * The gates that decide *whether* a shop may be written to at all live in the
 * jobs (PushShopifyProductsJob for the scheduled sweep, PushShopifyProductJob
 * for the save-triggered push). This class is the write itself, shared so the
 * two cannot drift apart.
 *
 * Nothing here needs a user context: every read drops the authorization scope
 * and every local write is quiet. Quiet writes are also what stops our own
 * push from bouncing back — see ShopifyApplyService for the full echo-loop
 * reasoning.
 */
class ShopifyProductPushService
{
    public function __construct(private readonly ShopifyService $service) {}

    /**
     * What a push would change, without writing anything.
     *
     * @return array<string, mixed>|null Null when nothing would change.
     */
    public function diff(Products $product): ?array
    {
        return $this->service->getAdapter()->diffProduct($product);
    }

    /**
     * Push product-level fields, then re-read what Shopify now holds so our
     * fingerprint matches the remote state and the webhook this push triggers
     * is recognised as ours.
     */
    public function push(Products $product, ProductMappings $mapping): void
    {
        $this->service->getAdapter()->pushProduct($product);
        $this->reapply((string) $mapping->external_product_id);
    }

    /**
     * Record that local and remote agree, so the row stops looking
     * locally-edited on every subsequent run.
     */
    public function markReconciled(ProductMappings $mapping): void
    {
        $mapping->last_synced_at = Carbon::now();
        $mapping->saveQuietly();
    }

    /**
     * Push variant prices for a product whose catalogue rows were edited here.
     *
     * Deliberately independent of the product-level diff: a price-only edit
     * leaves name and description untouched, so gating this on diff() would
     * make prices the one thing local edits could never send.
     *
     * @return int How many variants were actually written.
     */
    public function pushVariants(Products $product): int
    {
        $catalogs = ProductCatalogs::withoutGlobalScope(AuthorizationScope::class)
            ->withoutGlobalScope(LimitScope::class)
            ->where('marketplace_product_id', $product->id)
            ->get();

        $pushed = 0;

        foreach ($catalogs as $catalog) {
            $mapping = ProductCatalogMappings::withoutGlobalScope(AuthorizationScope::class)
                ->withoutGlobalScope(LimitScope::class)
                ->where('marketplace_provider_id', $this->service->provider->id)
                ->where('marketplace_product_catalog_id', $catalog->id)
                ->first();

            if (! $mapping) {
                continue;
            }

            $remotePrice = (float) data_get($catalog->args, 'shopify.pushed_price', -1);

            if (abs((float) $catalog->price - $remotePrice) < 0.005) {
                continue;
            }

            try {
                $result = $this->service->getAdapter()->pushCatalog($catalog);

                if (($result['pushed'] ?? false) === true) {
                    $this->rememberPushedPrice($catalog);
                    $pushed++;
                }
            } catch (\Throwable $e) {
                Log::warning(__METHOD__.' - variant price push failed', [
                    'catalog_id' => $catalog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $pushed;
    }

    /**
     * Remember the price we just sent, so an unchanged variant is skipped on
     * the next run instead of being rewritten every time.
     */
    private function rememberPushedPrice(ProductCatalogs $catalog): void
    {
        $args = is_array($catalog->args) ? $catalog->args : [];
        $args['shopify']['pushed_price'] = (float) $catalog->price;

        $catalog->updateQuietly(['args' => $args]);
    }

    /**
     * Re-read what Shopify now holds and apply it, so our fingerprint matches
     * the remote state and the webhook our push just triggered is a no-op.
     */
    private function reapply(string $externalId): void
    {
        $node = data_get(
            $this->service->getAdapter()->getClient()->query(ShopifyGraphQL::PRODUCT_BY_ID, ['id' => $externalId]),
            'product'
        );

        if (is_array($node)) {
            $this->service->applier()->applyProduct($this->service->getAdapter()->normalizeProductData($node));
        }
    }
}
