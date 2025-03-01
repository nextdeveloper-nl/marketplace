<?php

namespace NextDeveloper\Marketplace\Actions\Accounts;

use NextDeveloper\Commons\Actions\AbstractAction;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Marketplace\Database\Models\Accounts;

/**
 * Class DisableService
 *
 * This class handles the disabling of a service for an account.
 */
class DisableService extends AbstractAction
{
    /**
     * Events associated with disabling the service.
     */
    public const EVENTS = [
        'disable-service:NextDeveloper\Marketplace\Accounts'
    ];

    /**
     * DisableService constructor.
     *
     * @param Accounts $accounts The accounts model instance.
     * @throws NotAllowedException
     */
    public function __construct(Accounts $accounts)
    {
        $this->model = $accounts;
        parent::__construct();
    }

    /**
     * Handles the disabling of the service.
     *
     * This method updates the account to set the service as disabled and logs the progress.
     */
    public function handle(): void
    {
        $this->setProgress(0, 'Starting to disable service');

        $this->model->updateQuietly([
            'is_service_enabled' => false
        ]);

        $this->setProgress(100, 'Service disabled');
    }
}