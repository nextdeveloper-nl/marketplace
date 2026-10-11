# Product Catalog

The catalog model separates **what a product is** from **what you can buy** — a `Product` holds the marketing/content side, a `ProductCatalog` holds the priced, sellable variant. Mapping tables reconcile both against whatever ID an external storefront (Shopify, Ideasoft) uses for the same item.

## Key Capabilities

- Define a Product once (name, content, sales pitch, EULA, refund policy) and offer it as one or more priced ProductCatalog variants (price, SKU, stock)
- Support add-on/bundled products via a parent-product relationship
- Reconcile internal Products/ProductCatalogs with external marketplace product/variant IDs so sync jobs know what maps to what
- (Planned) let other platform modules auto-generate a marketplace listing for their own resources via the `Productable`/`Catalogable` traits

## Products

A `Products` row is the abstract, sellable item definition: `name`, `content`, `highlights`, `subscription_type`, `product_type`, `sales_pitch`, `eula`, `refund_policy`, `is_service`, `is_approved`, and the owning `marketplace_market_id`/`marketplace_provider_id`. A product can reference a `parent_marketplace_product_id` and be flagged `is_additional_product` — this is how bundled add-ons (e.g. "extra warranty" on top of a base product) are modeled. `ProductsPerspective` gives a denormalized, read-oriented view for listing.

## Product Catalogs

A `ProductCatalogs` row is the actual purchasable variant of a Product: `price`, `sku`, `quantity_in_inventory`, `trial_date`, `features`, `common_currency_id`, belonging to a Product. Subscriptions and Order Items reference the catalog row, not the Product directly — the catalog is "the SKU," the Product is "the listing." `ProductCatalogsPerspective` mirrors this for reporting/listing.

## Mapping External Products

`ProductMappings` and `ProductCatalogMappings` link an internal Product/ProductCatalog to its `external_product_id`/`external_catalog_id` on a specific Provider. This is the reconciliation table sync jobs read and write: when a Shopify webhook arrives for a product, the mapping table is how the module finds (or creates) the matching internal record, and vice versa when pushing a local price change out to Shopify. `MappingExternalProduct` (a helper, not a model) provides the lookup functions (`mapProductCatalog`, `mapMainProduct`, `mapOrderStatus`) used across the sync pipeline, logging a warning whenever an external ID can't be resolved to an internal record instead of failing the whole sync run.

## Extending the Catalog from Other Modules

`Productable` and `Catalogable` (traits) and `Common\Products`/`Common\ProductCatalog` (abstract base classes) are the intended extension point for other platform modules to auto-generate a marketplace listing when their own resource is created — for example, a Hotels module turning a "hotel room" into a catalog entry automatically. **As of this writing these are scaffolding only** — the `create()`/`addCatalog()` method bodies are empty stubs, not implemented behavior. Treat this as a documented but not-yet-active extension mechanism.

## API Examples

**Create a Product**

```
POST /marketplace/products
```
```json
{
  "name": "Pro Plan Add-on",
  "marketplace_market_id": "b1c2...-uuid",
  "product_type": "digital",
  "is_service": true
}
```

**Add a priced catalog variant**

```
POST /marketplace/product-catalogs
```
```json
{
  "marketplace_product_id": "c3d4...-uuid",
  "price": 19.99,
  "sku": "PRO-ADDON-M",
  "common_currency_id": "usd-currency-uuid"
}
```

**Map a catalog variant to its Shopify variant ID**

```
POST /marketplace/product-catalog-mappings
```
```json
{
  "marketplace_provider_id": "a1b2...-uuid",
  "marketplace_product_catalog_id": "c3d4...-uuid",
  "external_catalog_id": "gid://shopify/ProductVariant/123456789"
}
```

## Related Features

- [Sales & Marketing Integration](sales-and-marketing-integration.md) — this is why the catalog matters: CRM Quote Items already reference a `marketplace_product_id`/`marketplace_product_catalog_id` directly
- [Shopify Integration](shopify-integration.md) — how product/catalog sync and push actually happen for Shopify
- [Orders & Fulfillment](orders-and-fulfillment.md) — Order Items reference a ProductCatalog
- [Subscriptions](subscriptions.md) — a subscription is tied to a ProductCatalog
