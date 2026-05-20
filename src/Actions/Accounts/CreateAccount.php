<?php

namespace NextDeveloper\Marketplace\Actions\Accounts;

use NextDeveloper\Commons\Actions\AbstractAction;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\IAM\Database\Models\Accounts as IamAccounts;
use NextDeveloper\Marketplace\Database\Models\Accounts;

/**
 * Backfills a Marketplace account for an existing IAM account.
 *
 * The DB trigger creates child rows inactive; this action is used to manually
 * create a child row for historical IAM accounts and flags it active.
 */
class CreateAccount extends AbstractAction
{
    public const EVENTS = [
        'created:NextDeveloper\Marketplace\Accounts',
    ];

    /**
     * @throws NotAllowedException
     */
    public function __construct(IamAccounts $iamAccount)
    {
        $this->model = $iamAccount;
        parent::__construct();
    }

    public function handle(): void
    {
        $this->setProgress(0, 'Starting to create marketplace account');

        Accounts::withoutGlobalScopes()->firstOrCreate(
            ['iam_account_id' => $this->model->id],
            ['is_service_enabled' => true]
        );

        $this->setProgress(100, 'Marketplace account created');
    }
}
