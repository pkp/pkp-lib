<?php

/**
 * @file classes/task/ProcessQueueJobs.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProcessQueueJobs
 * @ingroup tasks
 *
 * @brief Class to process queue jobs via the schedular task
 */

namespace PKP\task;

use APP\core\Application;
use PKP\config\Config;
use PKP\scheduledTask\ScheduledTask;
use PKP\Domains\Jobs\JobRunner;

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
        if ( Application::isUnderMaintenance() ) {
            return true;
        }

        $jobQueue = app('pkpJobQueue');

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if ($jobBuilder->count() <= 0) {
            return true;
        }

        // Run queue jobs on CLI
        if( runOnCLI('runScheduledTasks.php') ) {

            while ($jobBuilder->count()) {
                $jobQueue->runJobInQueue();
            }

            return true;
        }

        // Run queue jobs off CLI
        if ( Config::getVar('queues', 'job_runner', false) ) {

            (new JobRunner($jobQueue))
                ->withMaxExecutionTimeConstrain()
                ->withMaxJobsConstrain()
                ->withMaxMemoryConstrain()
                ->withEstimatedTimeToProcessNextJobConstrain()
                ->processJobs($jobBuilder);
        }

        return true;
    }
}
