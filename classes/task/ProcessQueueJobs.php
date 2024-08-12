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
 * @brief Class to process queue jobs via the scheduler task
 */

namespace PKP\task;

use PKP\config\Config;
use PKP\core\PKPContainer;
use PKP\queue\JobRunner;
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

        // When processing queue jobs vai schedule task in CLI mode
        // will process a limited number of jobs at a single time
        if (PKPContainer::getInstance()->runningInConsole()) {
            $maxJobCountToProcess = abs(Config::getVar('queues', 'job_runner_max_jobs', 30));
            
            while ($jobBuilder->count() && $maxJobCountToProcess) {
                $jobQueue->runJobInQueue();
                --$maxJobCountToProcess;
            }

            return true;
        }

        // We don't need to process jobs when the job runner is enabled
        if (Config::getVar('queues', 'job_runner', false)) {
            return true;
        }

        // Executes a limited number of jobs when processing a via web request mode
        (new JobRunner($jobQueue))
            ->withMaxExecutionTimeConstrain()
            ->withMaxJobsConstrain()
            ->withMaxMemoryConstrain()
            ->withEstimatedTimeToProcessNextJobConstrain()
            ->processJobs($jobBuilder);

        return true;
    }
}
