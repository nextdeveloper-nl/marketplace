# Subscriptions

A Marketplace Subscription is the recurring-access record tying an account/user to a priced catalog item for a period of time — the answer to "is this account currently entitled to this ProductCatalog, and until when?"

## Key Capabilities

- Grant an account or user access to a `ProductCatalog` for a defined time window
- Store arbitrary provider- or plan-specific subscription data alongside the core record
- Mark a subscription valid/invalid independently of its date window, for manual overrides
- Tag subscriptions for segmentation and reporting

## Subscriptions

A `Subscriptions` row (`marketplace_subscriptions`) links an account/user to a `ProductCatalogs` row via `subscription_starts_at`/`subscription_ends_at`, plus `subscription_data` (JSON, for anything plan- or provider-specific that doesn't warrant its own column), `is_valid` (a manual/computed override independent of the date window — e.g. to immediately revoke access without deleting the record), and `tags`.

## API Examples

**Subscribe an account to a catalog item**

```
POST /marketplace/subscriptions
```
```json
{
  "iam_account_id": "9d4e...-uuid",
  "marketplace_product_catalog_id": "c3d4...-uuid",
  "subscription_starts_at": "2026-09-13T00:00:00Z",
  "subscription_ends_at": "2027-09-13T00:00:00Z"
}
```

**Revoke access without deleting history**

```
PATCH /marketplace/subscriptions/{id}
```
```json
{
  "is_valid": false
}
```

## Related Features

- [Product Catalog](product-catalog.md) — a subscription always references a ProductCatalog
- [Accounts & Customers](accounts-and-customers.md) — subscriptions belong to an account
