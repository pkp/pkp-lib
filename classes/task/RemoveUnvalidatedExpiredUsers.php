<?php

/**
 * @file classes/task/RemoveUnvalidatedExpiredUser.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveUnvalidatedExpiredUser
 *
 * @ingroup tasks
 *
 * @brief Class to remove all unvalidated and expired users after validation timeout
 */

namespace PKP\task;

use APP\facades\Repo;
use Carbon\Carbon;
use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;

class RemoveUnvalidatedExpiredUsers extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.removeUnvalidatedExpiredUsers');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        // No need to remove invalidated users if validation requirement is turned off
        if (!Config::getVar('email', 'require_validation', false)) {
            return true;
        }

        $validationMaxDeadlineInDays = (int) Config::getVar('general', 'user_validation_period');

        if ($validationMaxDeadlineInDays <= 0) {
            return true;
        }

        $dateTillValid = Carbon::now()->startOfDay()->subDays($validationMaxDeadlineInDays);

        Repo::user()->deleteUnvalidatedExpiredUsers($dateTillValid);

        return true;
    }
}
