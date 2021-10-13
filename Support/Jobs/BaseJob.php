<?php

declare(strict_types=1);

/**
 * @file Support/Jobs/BaseJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseJob
 * @ingroup support
 *
 * @brief Abstract class for Jobs
 */

namespace PKP\Support\Jobs;

use APP\core\Application;
use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PKP\config\Config;

use Throwable;

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

    public function __construct()
    {
        $this->connection = $this->defaultConnection();
        $this->queue = Config::getVar('queues', 'default_queue', 'queue');
    }

    protected function defaultConnection(): string
    {
        if (!Application::isReady()) {
            return 'sync';
        }

        return Config::getVar('queues', 'default_connection', 'database');
    }

    abstract public function handle();

    public function failed(Throwable $e)
    {
        $jobArr = '';

        if ($this->job) {
            $jobArr = (string) $this->job->getRawBody();
        }

        app('queue.failer')->log(
            $this->connection,
            $this->queue,
            $jobArr,
            $e
        );
    }
}
