<?php

declare(strict_types=1);

/**
 * @file jobs/BaseJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseJob
 *
 * @ingroup support
 *
 * @brief Abstract class for Jobs
 */

namespace PKP\jobs;

use APP\core\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PKP\config\Config;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $connection;

    /**
     * The queue's name where the job will be consumed
     *
     * @var string
     */
    public $queue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of SECONDS to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 5;

    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Initiate the job
     */
    public function __construct()
    {
        $this->connection = $this->defaultConnection();
        $this->queue = Config::getVar('queues', 'default_queue', 'queue');
    }

    /**
     * Get the queue job default connection to execute
     */
    protected function defaultConnection(): string
    {
        if (Application::isUnderMaintenance()) {
            return 'sync';
        }

        return Config::getVar('queues', 'default_connection', 'database');
    }

    /**
     * handle the queue job execution process
     */
    abstract public function handle();
}
