# Ideasoft Integration

Ideasoft is the module's second storefront integration, alongside Shopify. **It is currently in development, not production-ready** — most of its adapter methods are scaffolding that throws rather than working code. This page documents what exists today so it isn't mistaken for a finished integration.

## Scope Compared to Shopify

`IdeasoftAdapter` implements `MarketplaceAdapter` + `ProductSyncAdapter` + `InventorySyncAdapter` only:

- **No fulfillment sync, by design** — Ideasoft owns fulfillment/shipping on its own side, so the platform never writes order status back to it. `updateOrderStatus()` is a documented, intentional no-op, not a gap.
- **No customer sync** — `CustomerSyncAdapter` isn't implemented.
- **No webhook support** — `getWebhookConfig()` reports `supported: false`, since push notifications from Ideasoft aren't confirmed as available.

## What's Real vs. Scaffolding

Only OAuth token storage and config validation are working code today. `fetchProducts()`, `fetchInventoryLevels()`, `pushProduct()`, and `pushCatalog()` all throw `\LogicException`, pending confirmation of the exact Ideasoft API endpoint paths — the public API docs for Ideasoft couldn't be resolved to concrete paths at the time this was built. `IdeasoftClient::getOrders()`, `pushProduct()`, and `pushStock()` likewise throw "not implemented yet." `pushInventoryLevel()` has real logic up to the point of calling the client, which then throws.

`IdeasoftService` mirrors `ShopifyService`'s cursor/sync-state/auto-pause structure, but there is no equivalent of `ShopifyApplyService`, no dedicated job classes, and no console commands wired to it yet.

## What This Means for Integrators

If you're connecting an Ideasoft store today, expect to only be able to store credentials and validate the connection — actual product, inventory, and order sync is not functional until the adapter methods above are implemented against confirmed Ideasoft endpoints. Treat any planning around Ideasoft sync cadence, webhooks, or push behavior as forward-looking, not current state.

## Related Features

- [Shopify Integration](shopify-integration.md) — the equivalent, fully-built integration, useful as a reference for what a finished Ideasoft integration should eventually look like
- [Providers & Markets](providers-and-markets.md) — an Ideasoft connection is a Provider with `adapter: ideasoft`
