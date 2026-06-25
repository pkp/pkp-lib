<?php

declare(strict_types=1);

/**
 * @file jobs/testJobs/CliWorkerTestJobWithHookNoContext.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CliWorkerTestJobWithHookNoContext
 *
 * @brief Site-level (non context-aware) counterpart of CliWorkerTestJobWithHook for CLI worker
 *   integration tests. It deliberately does NOT implement ContextAwareJob, so Queue::before commits
 *   the worker to the null/site context and skips reconcileCliContextAfterPluginLoad(). Fires the same
 *   PKPCliPluginLoadingTest::workerHook during execution.
 */

namespace PKP\jobs\testJobs;

use Illuminate\Bus\Batchable;
use PKP\config\Config;
use PKP\job\models\Job;
use PKP\jobs\BaseJob;
use PKP\plugins\Hook;

class CliWorkerTestJobWithHookNoContext extends BaseJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    /**
     * Initialize the job
     */
    public function __construct()
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Job::TESTING_QUEUE;
    }

    /**
     * Handle the queue job execution - fires the test hook
     *
     * @hook PKPCliPluginLoadingTest::workerHook
     */
    public function handle(): void
    {
        Hook::run('PKPCliPluginLoadingTest::workerHook', []);
    }
}
