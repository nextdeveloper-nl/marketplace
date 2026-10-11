# Providers & Markets

A **Provider** is one connected external storefront — a specific Shopify shop, an Ideasoft store, or any other channel the marketplace module can sync with. A **Market** is the top-level storefront grouping a merchant operates under (its domain, currency, language, country). Products, Providers, and Orders all belong to a Market.

## Key Capabilities

- Connect a new external store per tenant by creating a Provider row — this is a database/admin operation, not an environment-variable deployment step
- Store per-connection credentials (access token, client secret, webhook secret) encrypted at rest, separately from the rest of the connection config
- Control sync direction per entity type (pull-only, push-only, or two-way) via a per-Provider sync policy
- Track sync health per Provider per entity type — cursors, last success, consecutive failures — via Provider Sync States
- Group Providers, Products, and Orders under a Market representing one storefront/tenant instance

## Providers

A Provider record (`marketplace_providers`) represents one connection to an external channel: `name`, `url`, `adapter` (a key like `shopify` or `ideasoft`, resolved to an integration class by the adapter factory), `api_config` (JSON — non-secret settings such as `shop_domain`, `api_version`, `location_id`, `webhook_url`, and a per-entity `sync_policy` of `direction: pull|push|two-way`), and the owning `iam_account_id`/`iam_user_id`.

Credentials are deliberately kept out of `api_config` and live in three separately encrypted columns — `access_token_enc`, `client_secret_enc`, `webhook_secret_enc` — decrypted on demand via `Providers::getDecryptedAccessToken()` / `getDecryptedClientSecret()`. A decrypt failure (for example after a key rotation) returns `null` instead of throwing, so a bad connection degrades rather than crashes the request. `canPush()`/`canPull()` read the sync policy and default to **pull-only** when unset, so a newly connected store can never accidentally push data to a merchant's storefront until an operator explicitly opts in.

A Provider `hasMany` Products, Orders, ProductMappings, ProductCatalogMappings, and StatusMappings, and belongs to a Market, an Account, and a User.

## Provider Sync States

`ProviderSyncStates` is the checkpoint and health record for background sync — one row per Provider + entity type (e.g. `products`, `orders`, `inventory`). It holds `cursor_updated_at` (where the last successful sync left off), `last_run_at`/`last_success_at`, `consecutive_failures`, `last_error`, and `records_processed`. This is what lets a sync job resume from where it stopped instead of re-scanning everything, and what an operator checks to see whether a connection is healthy — see [`marketplace:shopify-status`](shopify-integration.md#console-commands) for the operator-facing view of this data.

## Markets

A Market (`marketplace_markets`) is the storefront/tenant instance a merchant operates: `name`, `common_domain_id`, `is_public`, `is_active`, plus currency/language/country and the owning `iam_account_id`. `MarketsPerspective` is a read-oriented reporting view adding denormalized `domain`, `currency`, `language`, `country`, `product_count`, `maintainer`, and `responsible` — useful for listing markets without joining out to related tables yourself.

## API Examples

**Connect a new Shopify store**

```
POST /marketplace/providers
```
```json
{
  "name": "Acme Store",
  "adapter": "shopify",
  "marketplace_market_id": "b1c2...-uuid",
  "api_config": {
    "shop_domain": "acme-store.myshopify.com",
    "api_version": "2026-04",
    "sync_policy": {
      "products": { "direction": "pull" },
      "inventory": { "direction": "two-way" },
      "orders": { "direction": "pull" }
    }
  }
}
```

**Check sync health for a provider**

```
GET /marketplace/provider-sync-states?filter[marketplace_provider_id]=b1c2...-uuid
```

**Create a Market**

```
POST /marketplace/markets
```
```json
{
  "name": "Acme US Storefront",
  "iam_account_id": "9d4e...-uuid",
  "is_public": true
}
```

## Related Features

- [Shopify Integration](shopify-integration.md) — what a Shopify-adapter Provider actually does
- [Ideasoft Integration](ideasoft-integration.md) — the other adapter Providers can point at
- [Product Catalog](product-catalog.md) — Products and ProductCatalogs live under a Provider/Market
- [Orders & Fulfillment](orders-and-fulfillment.md) — Orders are fetched per Provider
