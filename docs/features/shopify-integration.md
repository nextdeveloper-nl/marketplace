# Shopify Integration

Shopify is the flagship, production integration: full two-way sync for products, inventory, orders, fulfillment, and customers, backed by GraphQL, webhooks, and a set of safety gates that keep an automated sync from ever silently wrecking a merchant's live storefront.

## Key Capabilities

- Pull products, inventory, orders, and customers from Shopify on a schedule, resuming from a durable cursor
- Push local inventory changes, fulfillment/cancellation/refund status, and (when explicitly approved) product/price changes back to Shopify
- Process Shopify webhooks for near-real-time updates, with automatic re-registration if Shopify drops a subscription
- Guard every destructive or bulk write with an explicit safety mechanism — compare-and-swap inventory writes, an approval gate for catalogue push, and a change-ratio circuit breaker
- Give operators a health dashboard and manual controls via console commands

## The Adapter Pattern

`MarketplaceAdapter` is the base contract every provider integration implements: `authenticate()`, `fetchOrders()`, `updateOrderStatus()`, `normalizeOrderData()`, `getWebhookConfig()`. Four optional capability interfaces layer on top, and a provider only implements the ones it can actually back:

- **ProductSyncAdapter** — `fetchProducts()`, `pushProduct()`, `pushCatalog()`, `normalizeProductData()`
- **InventorySyncAdapter** — `fetchInventoryLevels()`, `pushInventoryLevel()` (compare-and-swap, to avoid oversell)
- **FulfillmentAdapter** — `createFulfillment()`, `cancelOrder()`, `refundOrder()`
- **CustomerSyncAdapter** — `fetchCustomers()`, `normalizeCustomerData()`

`ShopifyAdapter` implements all five — a full integration. Compare this with [Ideasoft Integration](ideasoft-integration.md), which implements only a subset and mostly as scaffolding.

## Why Shopify Needs Extra Care

Two Shopify-specific realities shape a lot of this integration's design:

- **No write attribution.** Shopify sends our own writes straight back to us as webhooks, indistinguishable from a merchant's own edit. The adapter computes a `sync_hash` fingerprint of every payload so it can recognize "this is an echo of our own change" and avoid re-processing it in a loop.
- **Real money and real customers are on the other end.** A mapping bug that creates phantom products, oversells inventory, or overwrites the wrong customer isn't a rollback-and-retry problem — it's a support incident. Every write path below has a corresponding safety gate.

## Products, Inventory, Orders — Three Services

- **`ShopifyService`** is the per-connection orchestrator: owns the adapter instance, owns sync-state (durable cursors, never a "just use now()" flag that silently drops a missed window), and owns auto-pause/alerting — 3 consecutive failures fires a "degraded" event, 5 auto-pauses the connection entirely (deliberately below Shopify's own 8-failure webhook auto-delete threshold, so we notice before Shopify does).
- **`ShopifyApplyService`** applies one normalized record (product, variant, inventory level, customer, order, or deletion) to the local database. Both the scheduled pull jobs and the webhook processor call into this same service, so fingerprinting/staleness/ordering rules can't drift between the two code paths. Writes are quiet (no re-triggering outbound push observers).
- **`ShopifyProductPushService`** is the actual write-to-Shopify logic for products and variant prices, shared by the immediate single-product push and the scheduled bulk push.

## The Catalogue-Push Approval Gate

Pushing product/price changes to Shopify is the single most destructive action available, so it's fenced by three independent checks before anything is written:

1. **Sync policy** — `sync_policy.products.direction` on the Provider must allow push (default is pull-only).
2. **Explicit operator approval** — an operator must run a dry run (which computes a real diff), review it, then approve within 30 minutes; approval is stored as `api_config.product_push.approved_at`.
3. **Change-ratio circuit breaker** — if a run would touch more than 50% of the catalogue in one go, it aborts and requires `--force`, since that pattern usually means a mapping bug, not merchant intent.

Product push also only ever **updates** products that originated from Shopify — it never creates new ones there, since an invented product in a merchant's live store is not something you can quietly undo.

## Sync Jobs

Pull (FROM Shopify, on a schedule): `SyncShopifyProductsJob`, `SyncShopifyInventoryJob` (orders every ~5 min, inventory ~15 min, products/customers hourly, per job docblocks), `SyncShopifyOrdersJob`, `SyncShopifyCustomersJob`.

Push (TO Shopify): `PushShopifyInventoryJob` (compare-and-swap; scheduled reconcile sweep and dispatched immediately after a local product push), `PushShopifyFulfillmentJob` (order status back to Shopify), `PushShopifyProductJob` (fires ~10s after a local product/catalog save, pushing just that item), `PushShopifyProductsJob` (the scheduled bulk catalogue push, behind the approval gate above).

Housekeeping: `ProcessShopifyWebhookJob` drains the webhook inbox, re-fetches current state from the Admin API rather than trusting the delivered payload, and dead-letters an event after 5 failed attempts. `ShopifyDeletionSweepJob` nightly-diffs the full remote product ID set against local mappings to catch deletions that happened during downtime (Shopify has no `deleted_at`, and delete webhooks carry no other detail) — also guarded by a max-delete-ratio safety rail. `ShopifyWebhookHealthJob` re-registers any subscription Shopify silently dropped.

**Note:** `MarketplaceServiceProvider::bootSchedule()` currently has no cron entries wired in code — the cadences above describe each job's intent per its docblock, but actual scheduling must be configured externally (host app `Kernel.php` or ops crontab). Confirm the live schedule before relying on a specific cadence.

## Webhooks

`ShopifyWebhookRegistrar` reconciles a shop's live webhook subscriptions against everything `ShopifyAdapter` needs (~24 topics: products, inventory, orders, fulfillments, customers, locations, app lifecycle, plus 3 mandatory GDPR compliance topics). This exists because Shopify silently deletes a subscription after 8 consecutive delivery failures — without active reconciliation, a short outage can turn into permanent, silent data loss for that topic.

## Console Commands

- **`marketplace:sync-shopify`** — pull from Shopify. `--entity=products|inventory|orders|customers|all`, `--provider=`, `--full` (ignore cursor, full backfill), `--dry-run`, `--queue`.
- **`marketplace:push-shopify`** — push to Shopify. `--entity=inventory|fulfillment|products|all` (note: `all` deliberately excludes `products` — catalogue push must be named explicitly), `--provider=`, `--dry-run`, `--approve`/`--revoke` (the catalogue-push gate), `--force` (override the change-ratio guard), `--queue`.
- **`marketplace:shopify-status`** — read-only per-shop health: cursors, last success, consecutive failures/last error per entity, webhook pending/dead-letter/processed-24h counts, mapped record counts, and orders currently in a sync-error state. `--provider=`, `--json`.
- **`marketplace:shopify-webhooks`** — `--process` (drain inbox), `--register` (create missing subscriptions), `--health` (re-register dropped ones), `--sweep` (deletion reconciliation), `--list`, `--dead-letters`, `--replay=<id|all>`, `--provider=`, `--dry-run`, `--batch=200`.

## Credentials & Config

There are no `SHOPIFY_*` environment variables — each connection's credentials and settings live per-row on the `marketplace_providers` table (`shop_domain`, `client_id`, `api_version`, `location_id`, `webhook_url`, `sync_policy` in `api_config`, plus the encrypted token columns). Connecting a new Shopify store is a per-tenant database operation, not a deployment-time env var change. See [Providers & Markets](providers-and-markets.md).

## A Note on Trendyol GoYemek

A prior `TrendyolGoYemekAdapter`/`TrendyolGoYemekService` integration existed and has been removed from the codebase. It is not documented as a current provider.

## API Examples

**Trigger an inventory pull for one provider**

```bash
php artisan marketplace:sync-shopify --entity=inventory --provider=a1b2...-uuid
```

**Dry-run then approve a bulk catalogue push**

```bash
php artisan marketplace:push-shopify --entity=products --provider=a1b2...-uuid --dry-run
php artisan marketplace:push-shopify --entity=products --provider=a1b2...-uuid --approve
php artisan marketplace:push-shopify --entity=products --provider=a1b2...-uuid
```

**Check connection health**

```bash
php artisan marketplace:shopify-status --provider=a1b2...-uuid --json
```

## Related Features

- [Providers & Markets](providers-and-markets.md) — a Shopify connection is a Provider with `adapter: shopify`
- [Product Catalog](product-catalog.md) — what gets synced/pushed
- [Orders & Fulfillment](orders-and-fulfillment.md) — order pull and fulfillment push
- [Accounts & Customers](accounts-and-customers.md) — Shopify customer resolution and CRM promotion
- [Ideasoft Integration](ideasoft-integration.md) — the other, currently partial, adapter
