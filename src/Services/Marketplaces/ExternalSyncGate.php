<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces;

use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Products;

/**
 * Who may link a product to, or sync it with, an external marketplace (eBay, Shopify).
 *
 * The generic `update` policy is the wrong gate for this: for a PUBLIC product it refuses everyone
 * except the exact user that created it (same user and same account), because it protects editing
 * the product record. A market owner whose public product was created by a colleague, an import or
 * another process was therefore refused even though their role may update products.
 *
 * Linking and syncing do not edit the product through the API; they create mappings and exchange
 * values with the external listing. So the rule here is: the product belongs to the caller's current
 * account, and the caller's role may update products of that account (the role permission and the
 * account ownership check of the normal policy, without the creator-only rule for public products).
 */
class ExternalSyncGate
{
    /**
     * @throws NotAllowedException With the specific reason when the caller may not manage this product.
     */
    public static function assertCanManage(Products $product): void
    {
        // Internal callers (jobs, commands) run with the roles check bypassed on purpose.
        if (UserHelper::bypassRolesCheck()) {
            return;
        }

        $account = UserHelper::currentAccount();

        if (! $account || (int) $product->iam_account_id !== (int) $account->id) {
            throw new NotAllowedException('This product belongs to another account, so you cannot link or sync it.');
        }

        // Ask the normal policy about an equivalent, non-public copy: that keeps the role permission and
        // the account check but drops the "only the creator may update a public product" rule.
        $probe = $product->replicate();
        $probe->is_public = false;
        $probe->iam_account_id = $product->iam_account_id;

        if (! UserHelper::can('update', $probe)) {
            throw new NotAllowedException('Your role is not allowed to change products, so you cannot link or sync this one.');
        }
    }
}
