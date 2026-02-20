<?php


/**
 * @file jobs/testJobs/CliWorkerTestJobWithHook.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CliWorkerTestJobWithHook
 *
 * @brief Test job for CLI worker integration tests - fires hooks during execution
 */

namespace PKP\jobs\testJobs;

use Illuminate\Bus\Batchable;
use PKP\config\Config;
use PKP\job\models\Job;
use PKP\jobs\BaseJob;
use PKP\plugins\Hook;
use PKP\queue\ContextAwareJob;

class CliWorkerTestJobWithHook extends BaseJob implements ContextAwareJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    /**
     * The context ID for this job
     */
    public int $contextId;

    /**
     * Initialize the job
     */
    public function __construct(int $contextId)
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Job::TESTING_QUEUE;
        $this->contextId = $contextId;
    }

    /**
     * Get the context ID for this job
     */
    public function getContextId(): int
    {
        return $this->contextId;
    }

    /**
     * Handle the queue job execution - fires the test hook
     */
    public function handle(): void
    {
        Hook::run('PKPCliPluginLoadingTest::workerHook', []);
    }
}
