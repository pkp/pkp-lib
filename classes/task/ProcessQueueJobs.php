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
use PKP\core\PKPContainer;
use PKP\config\Config;
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

        $jobQueue = app('pkpJobQueue'); /** @var \PKP\core\PKPQueueProvider $jobQueue */

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if ($jobBuilder->count() <= 0) {
            return true;
        }

        // When processing queue jobs via schedule task in CLI mode
        // will process a limited number of jobs at a single time
        if (PKPContainer::getInstance()->runningInConsole('runScheduledTasks.php')) {
            $maxJobCountToProcess = abs(Config::getVar('queues', 'job_runner_max_jobs', 30));
            
            while ($jobBuilder->count() && $maxJobCountToProcess) {
                // if there is no more jobs to run, exit the loop
                if ($jobQueue->runJobInQueue() === false) {
                    break;
                }
                
                --$maxJobCountToProcess;
            }

            return true;
        }

        // Will never run the job runner in CLI mode
        if (PKPContainer::getInstance()->runningInConsole()) {
            return true;
        }

        // Executes a limited number of jobs when processing a request
        $jobRunner = app('jobRunner'); /** @var \PKP\queue\JobRunner $jobRunner */
        $jobRunner
            ->setCurrentContextId(Application::get()->getRequest()->getContext()?->getId())
            ->withMaxExecutionTimeConstrain()
            ->withMaxJobsConstrain()
            ->withMaxMemoryConstrain()
            ->withEstimatedTimeToProcessNextJobConstrain()
            ->processJobs($jobBuilder);

        return true;
    }
}
