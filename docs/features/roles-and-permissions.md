# Roles & Permissions

Marketplace defines seven roles (all under the `marketplace` DB prefix). Most reflect the general e-commerce/channel-sync side of the module; three reflect a restaurant/food-ordering vertical the module also serves.

## The Roles

| Role | Level | Scope |
| --- | --- | --- |
| `MarketplaceAdminRole` | 100 | Full CRUD across markets, products, catalogs, subscriptions, providers, orders, and mappings — full admin |
| `MarketplaceSuccessManagerRole` | 120 | Near-admin CRUD over markets/products/catalogs/orders — internal success-manager oversight |
| `MarketplaceVendorRole` | 120 | Near-admin CRUD over markets/products/catalogs/orders — merchant/vendor operator |
| `MarketplaceSalesPerson` | 140 | Read-mostly across accounts/items/subscriptions/orders, with account-update rights — a support/sales role |
| `MarketplaceUserRole` | 150 | Regular end user: read/subscribe access, plus create/update/delete on their own orders, scoped to their own account or public markets/catalogs |
| `MarketplaceRestaurantChefRole` | 150 | Currently identical to `MarketplaceUserRole` |
| `MarketplaceRestaurantWaiterRole` | 150 | Currently identical to `MarketplaceUserRole` |

## The Restaurant Roles Are Placeholders

`MarketplaceUserRole`, `MarketplaceRestaurantChefRole`, and `MarketplaceRestaurantWaiterRole` all share the same `NAME` (`marketplace-user`) and, as of this writing, byte-for-byte identical permission logic — the two restaurant-named classes are clones of `MarketplaceUserRole`, differentiated only by class name. Along with restaurant-oriented Order fields (`delivery_method`, `special_instructions`, `modifiers` — see [Orders & Fulfillment](orders-and-fulfillment.md)), this confirms the module is being extended toward a restaurant/food-ordering use case, but the chef- and waiter-specific permission logic hasn't been written yet. Don't assume chef/waiter have different effective permissions from a regular marketplace user until this is implemented.

## Related Features

- [Accounts & Customers](accounts-and-customers.md) — the account-level flag these roles operate within
- [Orders & Fulfillment](orders-and-fulfillment.md) — the restaurant-vocabulary order fields these roles are meant to eventually manage differently
