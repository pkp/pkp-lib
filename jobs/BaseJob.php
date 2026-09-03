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
     * When true, the job takes its timeout from the connection's configured `retry_after`
     * instead of the `$timeout` above, so that raising `[queues] retry_after` in
     * config.inc.php is enough to give the job more time to complete, with no code change.
     *
     * The derived value replaces any `$timeout` the job declares for itself, which keeps
     * the timeout of an opted in job below the window its reservation is held for however
     * `retry_after` is configured. A `$timeout` declared alongside this flag therefore only
     * applies on a connection that defines no `retry_after`, such as `sync`.
     *
     * Set this only on jobs that genuinely need it. Such jobs should be processed by a
     * worker daemon: neither the built-in job runner nor the task scheduler is able to
     * enforce a timeout on a job that is already running.
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
     * The derived value takes precedence over a timeout the job declares for itself, so
     * that `retry_after` is always the ceiling. Connections defining no `retry_after`,
     * such as `sync`, leave the declared timeout untouched.
     */
    protected function applyLongRunningTimeout(): void
    {
        // Not long running job, keep it's defined or inherited timeout and do not alter
        if (!$this->isLongRunning) {
            return;
        }

        $retryAfter = (int) config("queue.connections.{$this->connection}.retry_after");

        if ($retryAfter > 0) {
            $this->timeout = $retryAfter - static::TIMEOUT_REDUCTION;
        }
    }

    /**
     * handle the queue job execution process
     */
    abstract public function handle();
}
