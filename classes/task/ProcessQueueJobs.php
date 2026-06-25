<?php

/**
 * @file classes/task/ProcessQueueJobs.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProcessQueueJobs
 *
 * @brief Class to process queue jobs via the scheduler task
 */

namespace PKP\task;

use APP\core\Application;
use Illuminate\Support\ProcessUtils;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPContainer;
use PKP\scheduledTask\ScheduledTask;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessQueueJobs extends ScheduledTask
{
    /**
     * Sentinel returned by spawnWorkerBatch() when a batch child had to be killed for exceeding its
     * hard timeout (distinct from any real worker exit code), signalling the batch loop to stop.
     */
    protected const BATCH_SPAWN_FAILED = -1;

    /**
     * The max time schedule queue to process jobs via scheduler CLI
     */
    public const SCHEDULED_QUEUE_MAX_EXECUTION_TIME_ON_CLI = 50;

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.processQueueJobs');
    }


    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        // If processing of queue jobs via schedule task is disbaled
        // will not process any queue jobs via scheduler
        if (!Config::getVar('queues', 'process_jobs_at_task_scheduler', false)) {
            return true;
        }

        $jobQueue = app('pkpJobQueue'); /** @var \PKP\core\PKPQueueProvider $jobQueue */

        $jobBuilder = $jobQueue->getJobModelBuilder();

        if ($jobBuilder->count() <= 0) {
            return true;
        }

        // In CLI mode, process a bounded number of jobs per tick in fresh worker batches. A single
        // process can only load one context's plugins (see runBoundedWorkerBatches() below), so each
        // batch is a fresh `jobs.php work` child, looped under a job threshold and a wall-clock budget
        // so multiple contexts are drained per tick, each with its own plugin set.
        if (PKPContainer::getInstance()->runningInConsole()) {
            $this->runBoundedWorkerBatches(
                abs(Config::getVar('queues', 'job_runner_max_jobs', 30)),
                abs(Config::getVar('queues', 'scheduled_queue_max_execution_time', static::SCHEDULED_QUEUE_MAX_EXECUTION_TIME_ON_CLI)),
            );

            return true;
        }

        // We don't need to process jobs when the job runner is enabled
        if (Config::getVar('queues', 'job_runner', false)) {
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

    /**
     * Process pending jobs in bounded batches, each a FRESH worker process, for the CLI scheduler.
     *
     * In CLI a single process can only load one context's plugins (the $contextCommitted latch in
     * PKPQueueProvider::Queue::before, plus accumulated hooks that cannot be unregistered), so draining
     * several contexts correctly requires a fresh process per context. Each batch spawns `jobs.php work`
     * with --no-self-restart; that child loads its first job's context cleanly and exits on the first of:
     * a context change (Looping), --max-jobs, --max-time, or an empty queue (--stop-when-empty). We then
     * compare the job count to size the next batch and relaunch, until the per-tick $jobThreshold or the
     * $budgetSeconds wall-clock budget is reached, or a batch makes no progress. Returns jobs processed.
     */
    protected function runBoundedWorkerBatches(int $jobThreshold, int $budgetSeconds, ?string $queue = null): int
    {
        $queue = $queue ?: Config::getVar('queues', 'default_queue', 'queue');
        $budgetSeconds = max(1, min($budgetSeconds, 55)); // wall-clock safety; always under the 60s tick
        $deadline = $this->now() + $budgetSeconds;
        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
        $jobsTool = Core::getBaseDir() . '/lib/pkp/tools/jobs.php';
        $processed = 0;

        while (true) {
            $remainingTime = $deadline - $this->now();
            if ($remainingTime <= 1) {
                break; // out of wall-clock budget
            }

            $remainingQuota = $jobThreshold - $processed;
            if ($remainingQuota <= 0) {
                break; // per-tick job threshold reached
            }

            $before = $this->pendingJobCount($queue);
            if ($before <= 0) {
                break; // queue drained
            }

            // Initiate the worker as a FRESH process, sized to what is left of the job threshold.
            $spawned = $this->spawnWorkerBatch([
                $php,
                $jobsTool,
                'work',
                '--queue=' . $queue,
                '--max-jobs=' . $remainingQuota,
                '--max-time=' . max(1, (int) floor($remainingTime)),
                '--stop-when-empty',
                '--no-self-restart',
            ], $remainingTime + 5);

            if ($spawned === self::BATCH_SPAWN_FAILED) {
                break;
            }

            // The child usually quits at a context boundary before --max-jobs, so the count delta is
            // the only accurate measure of work done and is what sizes the next batch's quota.
            $delta = max(0, $before - $this->pendingJobCount($queue));
            $processed += $delta;

            if ($delta === 0) {
                break; // no progress (jobs reserved by another worker or failing) — avoid a busy loop
            }
        }

        return $processed;
    }

    /**
     * Current wall-clock time in fractional seconds. Seam for deterministic testing of the batch loop.
     */
    protected function now(): float
    {
        return microtime(true);
    }

    /**
     * Count pending jobs on the given queue. Seam for testing the batch loop without a database.
     */
    protected function pendingJobCount(string $queue): int
    {
        return app('pkpJobQueue')->forQueue($queue)->getJobModelBuilder()->count();
    }

    /**
     * Run a single bounded worker batch as a fresh child process; returns its exit code, or
     * BATCH_SPAWN_FAILED if it had to be killed for exceeding the hard timeout. Seam for testing.
     */
    protected function spawnWorkerBatch(array $command, float $hardTimeout): int
    {
        $process = Process::fromShellCommandline(
            implode(' ', array_map(fn ($arg) => ProcessUtils::escapeArgument($arg), $command))
        );
        // Hard backstop ABOVE the graceful --max-time, to kill a child stuck inside a single long job.
        $process->setTimeout($hardTimeout);

        try {
            return $process->run();
        } catch (ProcessTimedOutException $e) {
            return self::BATCH_SPAWN_FAILED;
        }
    }
}
