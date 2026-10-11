# Sales & Marketing Integration

This is the actual reason the module exists: sync products and customers in from an external storefront (Shopify, Ideasoft) so that data can drive the rest of the sales and marketing stack — quoting, opportunities, and campaigns — without anyone re-typing a product or a contact by hand. This page is the honest status of that goal: what already works, and what's still a direction rather than a shipped feature.

## Status at a Glance

| Goal | Status |
| --- | --- |
| Create CRM **Quotes** from synced marketplace products | **Built and working** |
| Create CRM **Opportunities** from synced marketplace products | **Not built** — only reachable indirectly via a Quote |
| Feed **marketing campaigns** from synced marketplace customers/products | **Not built** — no code exists yet |

## Quotes: Built Today

A CRM Quote Item can reference a marketplace product directly. `QuoteItemsCreateRequest` requires `marketplace_product_id` and `marketplace_product_catalog_id` (validated against `marketplace_products`/`marketplace_product_catalogs`), so a sales rep builds a quote by picking real, synced catalog items rather than describing a line item from scratch. From there:

- The quote item's price/currency is resolved from the linked `ProductCatalogs` row if not explicitly supplied
- `RecalculateQuote` (a CRM job) pulls live pricing from `ProductsPerspective`/`ProductCatalogsPerspective` to keep quote totals current with the marketplace catalog
- Converting an accepted quote to an invoice carries the marketplace reference forward: invoice items are created with `object_type = 'NextDeveloper\Marketplace\Database\Models\ProductCatalogs'` and `object_id` pointing at the catalog row, so the link from invoice back to the exact synced product/variant survives all the way to billing

This is the one piece of "sync it and it's easy to sell it" that is actually implemented — see [CRM's Sales Pipeline docs](../../../CRM/docs/features/sales-pipeline.md) for the quoting flow itself.

## Opportunities: Not Yet Connected

There is currently no direct link between a marketplace Product/ProductCatalog and a CRM Opportunity — no field on `Opportunities` for it, no service, no event listener. The only path a marketplace product has into an opportunity today is indirect: a Quote can be attached to an Opportunity (`crm_opportunity_id`), and that Quote's items can reference marketplace products — so the product reaches the opportunity by way of the quote, never directly.

If the goal is "create an opportunity with marketplace products attached," that requires new work: either a direct product reference on `Opportunities`/an `OpportunityItems`-style table, or a service that creates the Quote alongside the Opportunity automatically so the product link is implicit rather than a manual extra step.

## Marketing: Not Yet Connected

Nothing in this module or in CRM currently feeds marketplace data into a marketing/campaign platform. Specifically, none of the following exist yet:

- No field or filter lets a CRM Campaign or Target List be built from marketplace customers (`marketplace_customer_mappings`), marketplace orders, or marketplace products
- No listener pushes a newly-synced marketplace customer or product toward any external marketing/campaign system
- Building a target list today is a manual, one-at-a-time process (`POST /crm/targets` then `POST /crm/target-users` per contact) — there's no bulk-seed-from-marketplace step

For this to work as described — using synced storefront data to drive marketing — the missing piece is a way to turn a set of marketplace customers or product-buyers into a CRM Target List/Campaign audience, and (if "our marketing platform" is a separate system) an export or event bridge from Marketplace/CRM to it.

## Why This Matters for Prioritization

The Quotes integration proves the pattern works end to end: sync a product, reference it by ID from another module, keep pricing live via the perspective views, and carry the reference through to billing. The same shape — a foreign key to `marketplace_product_catalogs`/`marketplace_products`, resolved through the existing perspective views — is the natural template for wiring up Opportunities and marketing audiences next, rather than inventing a new mechanism.

## Related Features

- [Product Catalog](product-catalog.md) — the Products/ProductCatalogs that Quotes already reference
- [Accounts & Customers](accounts-and-customers.md) — `PromoteToCrm` is the only existing bridge from a synced marketplace customer into CRM, and it's customer-only, not campaign-aware
- [Shopify Integration](shopify-integration.md) / [Ideasoft Integration](ideasoft-integration.md) — where the product/customer data driving all of this actually comes from
