<?php

/**
 * @file classes/task/ProcessQueueJobs.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProcessQueueJobs
 *
 * @ingroup tasks
 *
 * @brief Class to process queue jobs via the schedular task
 */

namespace PKP\task;

use APP\core\Application;
use PKP\config\Config;
use PKP\queue\JobRunner;
use PKP\scheduledTask\ScheduledTask;

class ProcessQueueJobs extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('admin.scheduledTask.processQueueJobs');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions()
    {
        if (Application::isUnderMaintenance() || !Config::getVar('queues', 'job_runner', true)) {
            return true;
        }

        $jobQueue = app('pkpJobQueue');

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if ($jobBuilder->count() <= 0) {
            return true;
        }

        // Executes all pending jobs when running the runScheduledTasks.php on the CLI
        if (runOnCLI('runScheduledTasks.php')) {
            while ($jobBuilder->count()) {
                $jobQueue->runJobInQueue();
            }

            return true;
        }

        // Executes a limited number of jobs when processing a request
        (new JobRunner($jobQueue))
            ->withMaxExecutionTimeConstrain()
            ->withMaxJobsConstrain()
            ->withMaxMemoryConstrain()
            ->withEstimatedTimeToProcessNextJobConstrain()
            ->processJobs($jobBuilder);

        return true;
    }
}
