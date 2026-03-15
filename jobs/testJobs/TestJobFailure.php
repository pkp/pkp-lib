<?php

declare(strict_types=1);

/**
 * @file jobs/testJobs/TestJobFailure.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestJobFailure
 *
 * @brief Example failed TestJob
 */

namespace PKP\jobs\testJobs;

use Exception;
use Illuminate\Bus\Batchable;
use PKP\config\Config;
use PKP\job\models\Job;
use PKP\jobs\BaseJob;

class TestJobFailure extends BaseJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     * 
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 1;

    /**
     * Initialize the job
     */
    public function __construct()
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Job::TESTING_QUEUE;
    }

    /**
     * handle the queue job execution process
     * 
     * @throws \Exception
     */
    public function handle(): void
    {
        throw new Exception('cli.test.job');
    }
}
