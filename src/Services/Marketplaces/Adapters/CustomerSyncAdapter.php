<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters;

use Illuminate\Support\Carbon;

/**
 * Capability interface for marketplaces that expose customer records.
 *
 * Customers are mapped onto `iam_users` via marketplace_customer_mappings;
 * there is no marketplace-local customer table.
 */
interface CustomerSyncAdapter
{
    /**
     * Fetch customers changed at or after the given moment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(Carbon $since): array;

    /**
     * Normalise a raw remote customer into our internal shape.
     *
     * Must return at least `external_id`, `external_updated_at`, `email` and
     * `phone`. Email may be null — guest checkouts have no customer record and
     * must not produce an iam_users row.
     *
     * @param  array<string, mixed>  $rawCustomer
     * @return array<string, mixed>
     */
    public function normalizeCustomerData(array $rawCustomer): array;
}
