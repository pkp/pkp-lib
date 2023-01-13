<?php

/**
 * @file classes/task/RemoveFailedJobs.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveFailedJobs
 * @ingroup tasks
 *
 * @brief Remove the much older failed jobs form the failed list
 */

namespace PKP\task;

use Carbon\Carbon;
use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;

class RemoveFailedJobs extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('admin.scheduledTask.removeFailedJobs');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions()
    {
        $cleanUpPeriod = (int) Config::getVar('queues', 'failed_job_clean_period', 180);

        // No need to run the clean up process if the cleaning period is not defined in config
        if ( !$cleanUpPeriod ) {
            return true;
        }

        app('queue.failer')->prune(Carbon::now()->subDays($cleanUpPeriod)->startOfDay());

        return true;
    }
}
