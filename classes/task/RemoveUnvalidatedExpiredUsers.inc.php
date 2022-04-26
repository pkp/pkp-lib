<?php

/**
 * @file classes/task/RemoveUnvalidatedExpiredUser.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
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
        if ( ! Config::getVar('general', 'remove_expired_users') ) {
            
            return;
        }
        
        $validationMaxDeadlineInDays = Config::getVar('email', 'validation_timeout') ?? 14;

        $dateTillValid = Carbon::now()->startOfDay()->subDays($validationMaxDeadlineInDays);

        Repo::user()->deleteUnvalidatedExpiredUsers($dateTillValid);

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\RemoveUnvalidatedExpiredUsers', '\RemoveUnvalidatedExpiredUsers');
}
