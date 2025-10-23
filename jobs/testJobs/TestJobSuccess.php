<?php

declare(strict_types=1);

/**
 * @file jobs/testJobs/TestJobSuccess.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestJobSuccess
 *
 * @brief Example successful TestJob
 */

namespace PKP\jobs\testJobs;

use Illuminate\Bus\Batchable;
use PKP\config\Config;
use PKP\job\models\Job;
use PKP\jobs\BaseJob;

class TestJobSuccess extends BaseJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
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
     * handle the queue job execution process
     */
    public function handle(): void
    {
    }
}
