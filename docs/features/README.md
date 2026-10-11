# Marketplace Platform Features

The module's purpose is to sync an external storefront (Shopify, Ideasoft) into the platform so that data can drive the rest of the sales and marketing stack — quoting, opportunities, and campaigns — instead of anyone re-entering a product or contact by hand. This is the feature documentation for how that sync works and how far the "easy to quote/sell/market" story actually reaches today. Each page below explains a feature area in plain language and includes brief API examples for the key operations in that area.

All API paths are relative to the platform's API base URL and prefixed with `/marketplace`. Resources are addressed by UUID and follow the same basic operations: list (`GET`), create (`POST`), show/update/delete (`GET` / `PATCH` / `DELETE` on `/{id}`), and custom actions (`POST /{id}/do/{action}`).

## Feature Areas

| Feature | What it covers |
| --- | --- |
| [Sales & Marketing Integration](sales-and-marketing-integration.md) | **Start here.** Why the module exists: turning synced products/customers into Quotes (built), Opportunities (not yet), and marketing campaigns (not yet) |
| [Providers & Markets](providers-and-markets.md) | Connecting an external storefront (Providers), the credentials/config that back a connection, and Markets as the top-level storefront grouping |
| [Product Catalog](product-catalog.md) | Products, priced/sellable ProductCatalogs, and the mapping tables that reconcile them with external product IDs |
| [Orders & Fulfillment](orders-and-fulfillment.md) | Orders, order items, status history, and status-mapping between provider and platform vocabularies |
| [Subscriptions](subscriptions.md) | Recurring access to a priced catalog item |
| [Accounts & Customers](accounts-and-customers.md) | Enabling marketplace per tenant, and reconciling external shoppers to internal CRM contacts |
| [Shopify Integration](shopify-integration.md) | The flagship two-way sync: products, inventory, orders, fulfillment, customers, webhooks, and the catalogue-push approval gate |
| [Ideasoft Integration](ideasoft-integration.md) | The in-progress Ideasoft connector — what's real today versus scaffolding |
| [Roles & Permissions](roles-and-permissions.md) | The seven marketplace roles, including the restaurant-vertical roles |

## How These Pages Are Organized

Each page follows the same structure: a short overview, a list of key capabilities, a deeper explanation of each sub-feature, brief API examples for the most common operations, and links to related feature areas.

## A Note on Scope

The Marketplace module supports two use cases side by side: a general e-commerce channel-sync integration (Shopify/Ideasoft product, inventory, and order sync) and a restaurant/food-ordering vertical (order fields like `delivery_method`, `special_instructions`, `modifiers`, and dedicated chef/waiter roles). Some pieces documented here are fully built and in production (Shopify sync), and some are early scaffolding not yet wired up (Ideasoft product/inventory sync, the chef/waiter role differentiation, the `Productable`/`Catalogable` traits). Each page calls out which is which.
