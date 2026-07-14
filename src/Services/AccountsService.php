<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\Accounts;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractAccountsService;

/**
 * This class is responsible from managing the data for Accounts
 *
 * Class AccountsService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class AccountsService extends AbstractAccountsService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    public static function suspend(Accounts $account): Accounts
    {
        $account->update([
            'is_service_enabled' => false,
        ]);

        return $account->fresh();
    }

    /**
     * Suspends the Marketplace account belonging to the given IAM account,
     * if one exists. Marketplace accounts are opt-in, so a customer without
     * one is a no-op here, not an error.
     */
    public static function suspendWithIamAccount(\NextDeveloper\IAM\Database\Models\Accounts $account): ?Accounts
    {
        $marketplaceAccount = Accounts::withoutGlobalScope(AuthorizationScope::class)
            ->where('iam_account_id', $account->id)
            ->first();

        if (!$marketplaceAccount) {
            return null;
        }

        return self::suspend($marketplaceAccount);
    }
}