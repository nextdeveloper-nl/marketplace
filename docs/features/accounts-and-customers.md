# Accounts & Customers

Marketplace's own Accounts layer is a thin opt-in flag on top of platform IAM Accounts. Alongside it sits a separate concern: reconciling an external storefront's shoppers (a Shopify customer, for instance) with internal platform users, and optionally promoting a synced shopper into a real CRM contact.

## Key Capabilities

- Turn marketplace features on/off per IAM account with a single flag
- Suspend marketplace access for an account, safely, even if it never opted in
- Deduplicate an external shopper against existing platform users by mapping, then email, then phone, before creating a new identity
- Keep synced shoppers scoped to their own tenant, so one shopper's email never cross-links unrelated merchants' customers
- Explicitly promote a synced shopper into a CRM contact, as an opt-in step rather than automatic

## Marketplace Accounts

A `marketplace_accounts` row is minimal: `iam_account_id` plus `is_service_enabled` — literally just "is marketplace turned on for this tenant." `EnableService`/`DisableService` are simple event-triggered actions that flip this flag. `AccountsService::suspend()` / `suspendWithIamAccount()` provide a no-op-safe way to disable marketplace for an IAM account that may not even have a `marketplace_accounts` row yet.

## Customer Mappings

`CustomerMappings` links an external customer (`external_customer_id`, scoped per Provider/Market) to an internal `iam_user_id`, tracking `sync_hash`/`external_updated_at`/`last_synced_at` so repeat syncs can detect what actually changed. This table is sync/backend-only today — there is no public `/marketplace/customer-mappings` route, so mappings are created and read internally by the sync pipeline, not managed directly via the API.

`ShopifyCustomerResolver` implements the dedupe logic used to build these mappings, in order:
1. An existing mapping for this external customer already exists — use it.
2. No mapping, but the email matches an existing user **within the same merchant account** — link to that user.
3. No email match, but an E.164-normalized phone number matches within the account — link to that user.
4. No match at all — create a new platform identity: `is_registered = false` (it can't log in), tagged `marketplace-shopify-customer`.

Matching is deliberately scoped per account so that, say, two unrelated merchants both having a customer with the same email never get linked to the same platform user.

## Promoting a Shopper to a CRM Contact

`PromoteToCrm` is the explicit, opt-in action that turns a synced marketplace shopper into an actual CRM contact (a `crm_users` row). It's find-or-create — a platform listener already creates a base `crm_users` row for every new `iam_user` — and it asserts account membership first, so a shopper can't be promoted into the wrong tenant's CRM. Promoted users are tagged `crm-promoted` rather than getting a dedicated column, keeping the CRM schema unchanged.

## API Examples

**Enable marketplace for an account**

```
POST /marketplace/accounts
```
```json
{
  "iam_account_id": "9d4e...-uuid",
  "is_service_enabled": true
}
```

**Disable marketplace for an account**

```
POST /marketplace/accounts/{id}/do/disable-service
```

## Related Features

- [Sales & Marketing Integration](sales-and-marketing-integration.md) — `PromoteToCrm` is currently the *only* bridge from a synced customer into CRM, and it stops at creating a contact — no campaign/marketing link exists yet
- [Providers & Markets](providers-and-markets.md) — accounts own Providers and Markets
- [Shopify Integration](shopify-integration.md) — where customer sync/mapping actually happens
