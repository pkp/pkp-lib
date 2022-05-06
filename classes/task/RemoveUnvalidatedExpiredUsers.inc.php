<?php

/**
 * @file classes/task/RemoveUnvalidatedExpiredUser.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveUnvalidatedExpiredUser
 * @ingroup tasks
 *
 * @brief Class to remove all unvalidated and expired users after validation timeout
 */

namespace PKP\task;

use Carbon\Carbon;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;

class RemoveUnvalidatedExpiredUsers extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('admin.scheduledTask.removeUnvalidatedExpiredUsers');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions()
    {   
        $validationMaxDeadlineInDays = (int) Config::getVar('general', 'user_validation_period');

        if ( $validationMaxDeadlineInDays <= 0 ) {
            
            return;
        }

        $dateTillValid = Carbon::now()->startOfDay()->subDays($validationMaxDeadlineInDays);

        Repo::user()->deleteUnvalidatedExpiredUsers($dateTillValid);

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\RemoveUnvalidatedExpiredUsers', '\RemoveUnvalidatedExpiredUsers');
}
