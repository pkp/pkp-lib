<?php

declare(strict_types=1);

/**
 * @file jobs/BaseJob.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseJob
 *
 * @brief Abstract class for Jobs
 */

namespace PKP\jobs;

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
     * The number of SECONDS deducted from the connection's `retry_after` when deriving
     * the timeout of a long running job.
     *
     * Laravel requires a job's timeout to be shorter than the connection's `retry_after`,
     * otherwise the job may be handed to a second worker while the first one is still
     * processing it.
     *
     * @see https://laravel.com/docs/12.x/queues#job-expirations-and-timeouts
     */
    protected const TIMEOUT_REDUCTION = 10;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of SECONDS to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * Whether this job may legitimately run for a long time on large installations.
     *
     * When true, the job derives its timeout from the connection's configured `retry_after`
     * whenever that leaves more room than the `$timeout` above, so that raising
     * `[queues] retry_after` in config.inc.php is enough to give the job more time to
     * complete, with no code change.
     *
     * Set this only on jobs that genuinely need it. Such jobs should be processed by a
     * worker daemon: neither the built-in job runner nor the task scheduler is able to
     * enforce a timeout on a job that is already running.
     *
     * A job opting in must not declare a `$timeout` of its own larger than the lowest
     * accepted `retry_after` minus TIMEOUT_REDUCTION, or it would outlive the window in
     * which its own reservation is held.
     */
    protected bool $isLongRunning = false;

    /**
     * Initialize the job
     */
    public function __construct()
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Config::getVar('queues', 'default_queue', 'queue');

        $this->applyLongRunningTimeout();
    }

    /**
     * Derive the timeout of a long running job from the connection's `retry_after`.
     *
     * The derived value never lowers a timeout the job declares for itself, and
     * connections that define no `retry_after`, such as `sync`, leave it untouched.
     */
    protected function applyLongRunningTimeout(): void
    {
        if (!$this->isLongRunning) {
            return;
        }

        $retryAfter = (int) config("queue.connections.{$this->connection}.retry_after");

        $this->timeout = max($this->timeout, $retryAfter - static::TIMEOUT_REDUCTION);
    }

    /**
     * handle the queue job execution process
     */
    abstract public function handle();
}
