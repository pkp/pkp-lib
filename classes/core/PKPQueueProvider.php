<?php

declare(strict_types=1);

/**
 * @file classes/core/PKPQueueProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueProvider
 * @ingroup core
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Queue\WorkerOptions;

use PKP\config\Config;
use PKP\Domains\Jobs\Job as PKPJobModel;

class PKPQueueProvider
{
    public function runJobsAtShutdown(): void
    {
        $disableRun = Config::getVar('queues', 'disable_jobs_run_at_shutdown', false);

        if ($disableRun || Application::isUnderMaintenance()) {
            return;
        }

        $job = PKPJobModel::isAvailable()
            ->notExceededAttempts()
            ->nonEmptyQueue()
            ->notQueue(PKPJobModel::TESTING_QUEUE)
            ->limit(1)
            ->first();

        if ($job === null) {
            return;
        }

        $laravelContainer = PKPContainer::getInstance();
        $options = new WorkerOptions(
            'default',
            $job->getDelay(),
            $job->getAllowedMemory(),
            $job->getTimeout(),
            $job->getSleep(),
            $job->getMaxAttempts(),
            $job->getForceFlag(),
            $job->getStopWhenEmptyFlag(),
        );

        $laravelContainer['queue.worker']->runNextJob(
            'database',
            $job->queue,
            $options
        );
    }

    public function register()
    {
        register_shutdown_function([$this, 'runJobsAtShutdown']);
    }
}
