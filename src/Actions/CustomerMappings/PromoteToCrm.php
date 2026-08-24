<?php

namespace NextDeveloper\Marketplace\Actions\CustomerMappings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Actions\AbstractAction;
use NextDeveloper\IAM\Database\Models\AccountUsers;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\Marketplace\Database\Models\CustomerMappings;

/**
 * Promotes a synced Shopify shopper into the merchant's CRM.
 *
 * Opt-in on purpose. Pulling every shopper into the CRM automatically is the
 * duplicate-contact complaint that runs through every integration in this
 * category, so the sync only ever creates the identity and the mapping, and a
 * human (or an explicit bulk run) decides who becomes a CRM contact.
 *
 * Two scope traps this has to respect:
 *
 * 1. CRM visibility is driven by the lowest-level CRM role's apply(), whose
 *    generic tail filters on iam_user_id alone. A contact that is not attached
 *    to the connecting account would therefore follow the shopper across
 *    accounts, so account membership is asserted before anything is written.
 * 2. crm_users is an EXTENSION of iam_users, not a contact record, and a
 *    platform listener already creates one for every new user. Promotion must
 *    therefore be idempotent — find-or-create, never blind insert, or a second
 *    run gives one person two CRM rows.
 */
class PromoteToCrm extends AbstractAction
{
    public const EVENTS = [
        'promote-to-crm:NextDeveloper\Marketplace\CustomerMappings',
    ];

    public function __construct(CustomerMappings $mapping, $params = null, $previous = null)
    {
        $this->model = $mapping;

        parent::__construct($params, $previous);
    }

    public function handle(): void
    {
        $this->setProgress(0, 'Promoting Shopify customer to CRM');

        $mapping = $this->model;

        $user = Users::withoutGlobalScope(AuthorizationScope::class)->find($mapping->iam_user_id);

        if (! $user) {
            $this->setFinishedWithError('The mapped user no longer exists.');

            return;
        }

        $accountId = (int) $mapping->iam_account_id;

        if ($accountId <= 0) {
            $this->setFinishedWithError('The customer mapping has no owning account.');

            return;
        }

        $this->setProgress(25, 'Checking account membership');

        // Trap 1: without this the CRM record follows the person across
        // accounts instead of belonging to the merchant who synced them.
        $isMember = AccountUsers::withoutGlobalScope(AuthorizationScope::class)
            ->where('iam_account_id', $accountId)
            ->where('iam_user_id', $user->id)
            ->exists();

        if (! $isMember) {
            $this->setFinishedWithError(
                'User '.$user->uuid.' is not a member of account '.$accountId.'; refusing to create a CRM record that would leak across accounts.'
            );

            return;
        }

        $this->setProgress(60, 'Creating the CRM extension record');

        // Trap 2: a listener already creates crm_users for every new iam_user,
        // so this is find-or-create rather than an insert.
        $existing = DB::table('crm_users')->where('iam_user_id', $user->id)->first();

        if (! $existing) {
            DB::table('crm_users')->insert([
                'iam_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info(__METHOD__.' - created CRM record for Shopify shopper', [
                'iam_user_id' => $user->id,
                'iam_account_id' => $accountId,
                'marketplace_provider_id' => $mapping->marketplace_provider_id,
            ]);
        }

        $this->setProgress(85, 'Recording the promotion on the mapping');

        $mapping->updateQuietly([
            'last_synced_at' => now(),
        ]);

        // Tagged rather than flagged: there is no promoted_at column, and the
        // tag is what a CRM view can filter on to find shoppers that came from
        // a marketplace connection.
        $tags = (array) ($user->tags ?? []);

        if (! in_array('crm-promoted', $tags, true)) {
            $tags[] = 'crm-promoted';
            $user->updateQuietly(['tags' => $tags]);
        }

        $this->setProgress(100, 'Customer promoted to CRM');
    }
}
