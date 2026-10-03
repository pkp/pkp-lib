<?php

/**
 * @file classes/task/ProcessQueueJobs.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProcessQueueJobs
 *
 * @brief Class to process queue jobs via the scheduler task
 */

namespace PKP\task;

use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;

class ProcessQueueJobs extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.processQueueJobs');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        // If processing of queue jobs via schedule task is disbaled
        // will not process any queue jobs via scheduler
        if (!Config::getVar('queues', 'process_jobs_at_task_scheduler', false)) {
            return true;
        }

        $jobQueue = app('pkpJobQueue'); /** @var \PKP\core\PKPQueueProvider $jobQueue */

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if ($jobBuilder->count() <= 0) {
            return true;
        }

        // Scheduled tasks are only run from the CLI, where a limited
        // number of jobs are processed on each scheduler run
        $maxJobCountToProcess = abs(Config::getVar('queues', 'job_runner_max_jobs', 30));

        while ($jobBuilder->count() && $maxJobCountToProcess) {
            $jobQueue->runJobInQueue();
            --$maxJobCountToProcess;
        }

        return true;
    }
}
