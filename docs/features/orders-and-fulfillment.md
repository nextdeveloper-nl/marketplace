# Orders & Fulfillment

An Order represents one purchase — either synced in from an external storefront or created directly on the platform. Order fields also carry a restaurant/food-delivery vocabulary (`delivery_method`, `special_instructions`, `modifiers`) alongside standard e-commerce fields, since the module doubles as the backend for a restaurant-ordering vertical — see [Roles & Permissions](roles-and-permissions.md).

## Key Capabilities

- Fetch orders from any connected Provider on a schedule, or on demand per provider
- Track an order through its lifecycle: ordered → accepted → prepared → dispatched → delivered (or cancelled)
- Auto-calculate order totals for internally created orders from their line items
- Record every status change with a timestamped history entry
- Translate a provider's own status vocabulary to one normalized internal status per provider

## Orders

An `Orders` row (`marketplace_orders`) has `order_type` (`external` for synced orders, `internal` for platform-created ones — default `external`), lifecycle timestamps (`ordered_at`, `accepted_at`, `prepared_at`, `dispatched_at`, `delivered_at`, `cancelled_at`), a free-text `status` (default `Accepted`), `delivery_method` (`platform_delivery` / `self_pickup` / `restaurant_delivery`), money fields (`subtotal_amount`, `delivery_fee`, `service_fee`, `tax_amount`, `discount_amount`, `total_amount`), and sync bookkeeping (`raw_order_data`, `last_synced_at`, `sync_error_message`). `order_no` is a generated column combining the provider name and internal ID.

## Order Items

`OrderItems` are line items tied to a `ProductCatalogs` row, with `quantity`, `price_per_item`, `total_price`, `modifiers`, and `special_instructions` — the modifiers/instructions fields exist specifically for customizable restaurant-style line items (e.g. "no onions," "extra spicy"). `OrderItemsPerspective` is the read-oriented denormalized view for listing.

For **internal** orders only, `CalculatingOrderTotalAmount` (an event-driven action firing on `OrderItems` creation) syncs each item's price from its catalog, sums quantity × price across the order, applies the platform tax rate, and writes `subtotal_amount`/`tax_amount`/`total_amount` back onto the Order automatically — external/synced orders keep whatever totals the provider sent, since those are authoritative.

## Fetching Orders from Providers

`FetchProviderOrdersCommand` (`nextdeveloper:fetch-provider-orders`) is the generic, provider-agnostic entry point: for every active Provider with an adapter configured, it impersonates that Provider's owning account/user, resolves the right integration service through the adapter factory, and calls `fetchOrders($date)`. One provider failing to resolve or sync does not stop the run for the others. Shopify has its own, more capable order sync path — see [Shopify Integration](shopify-integration.md).

## Status History & Status Mapping

`OrderStatusHistories` records every `old_status → new_status` transition with `changed_at` and `notes`, giving a full audit trail per order. `StatusMappings` translates a provider's own status vocabulary (`external_status`) into the platform's `normalized_status`, per provider — this is how, for example, Shopify's "fulfilled" and a different provider's "shipped" both resolve to the same internal concept, so downstream logic only ever has to reason about normalized statuses.

## API Examples

**Fetch orders for all providers on demand**

```bash
php artisan nextdeveloper:fetch-provider-orders --date=2026-09-01
```

**List orders with their status history**

```
GET /marketplace/orders/{id}/order-status-history
```

**Create an internal order** (totals are computed automatically from the items)

```
POST /marketplace/orders
```
```json
{
  "order_type": "internal",
  "marketplace_market_id": "b1c2...-uuid",
  "delivery_method": "self_pickup"
}
```

**Add a status mapping for a new provider**

```
POST /marketplace/status-mappings
```
```json
{
  "marketplace_provider_id": "a1b2...-uuid",
  "external_status": "shipped",
  "normalized_status": "dispatched"
}
```

## Related Features

- [Product Catalog](product-catalog.md) — Order Items reference a ProductCatalog
- [Shopify Integration](shopify-integration.md) — order pull/push, fulfillment push, and the webhook pipeline for Shopify specifically
- [Roles & Permissions](roles-and-permissions.md) — the restaurant-vertical roles that create and manage orders
