<?php

declare(strict_types=1);

/**
 * @file classes/queue/JobRunner.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobRunner
 *
 * @brief   Synchronous job runner that will attempt to execute jobs at the end of web request
 *          life cycle. It class is designed to execute jobs when not running via a worker
 *          or a cron job.
 */

namespace PKP\queue;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PKP\config\Config;
use PKP\core\PKPQueueProvider;

class JobRunner
{
    /*
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Static flag to prevent multiple runs within the same request.
     */
    protected bool $jobProcessing = false;

    /**
     * The core queue job running service provider
     */
    protected PKPQueueProvider $jobQueue;

    /**
     * Should have max job number running constrain
     */
    protected bool $hasMaxJobsConstrain = false;

    /**
     * Max jobs to process in a single run
     */
    protected ?int $maxJobsToProcess = null;

    /**
     * Should have max run time constrain when processing jobs
     */
    protected bool $hasMaxExecutionTimeConstrain = false;

    /**
     * Max job processing run time in SECOND
     */
    protected ?int $maxTimeToProcessJobs = null;

    /**
     * Should have max consumed memory constrain when processing jobs
     */
    protected bool $hasMaxMemoryConstrain = false;

    /**
     * Max memory to consume when processing jobs in BYTE
     */
    protected ?int $maxMemoryToConsumed = null;

    /**
     * Should estimate next job possible processing time to allow next job to be processed/run
     */
    protected bool $hasEstimatedTimeToProcessNextJobConstrain = false;

    /**
     * Should estimate next job possible processing time to allow next job to be processed/run
     */
    protected int $jobProcessedOnRunner = 0;

    /**
     * Should the job runner run in context aware mode
     * 
     * This will allow the job runner to take into account the current context when processing jobs as 
     *  - If current request has context, only process jobs for which there context id in the payload
     *    that match it or the context id is null
     *  - If the current request has not context, only process jobs that has not context id or
     *    the context id is null
     */
    protected bool $runInContextAwareMode = true;

    /**
     * The current context ID
     */
    protected ?int $currentContextId = null;

    // Private constructor to prevent direct instantiation
    private function __construct() {}

     /*
     * Get the singleton instance
     */
    public static function getInstance(?PKPQueueProvider $jobQueue = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->jobQueue = $jobQueue ?? app('pkpJobQueue');
        }

        return self::$instance;
    }

    /*
     * Set/Update the job queue
     */
    public function setJobQueue(PKPQueueProvider $jobQueue): static
    {
        $this->jobQueue = $jobQueue;

        return $this;
    }

    /**
     * Set the max job number running constrain
     *
     */
    public function withMaxJobsConstrain(): self
    {
        $this->hasMaxJobsConstrain = true;

        if (! $this->getMaxJobsToProcess()) {
            $this->setMaxJobsToProcess(Config::getVar('queues', 'job_runner_max_jobs', 30));
        }

        return $this;
    }

    /**
     * Set the max allowed jobs number to process
     *
     */
    public function setMaxJobsToProcess(int $maxJobsToProcess): self
    {
        $this->maxJobsToProcess = $maxJobsToProcess;

        return $this;
    }

    /**
     * Get the max allowed jobs number to process
     *
     */
    public function getMaxJobsToProcess(): ?int
    {
        return $this->maxJobsToProcess;
    }

    /**
     * Set the max run time constrain when processing jobs
     *
     */
    public function withMaxExecutionTimeConstrain(): self
    {
        $this->hasMaxExecutionTimeConstrain = true;

        if (! $this->getMaxTimeToProcessJobs()) {
            $this->setMaxTimeToProcessJobs($this->deduceSafeMaxExecutionTime());
        }

        return $this;
    }

    /**
     * Set the max allowed run time to process jobs in SECONDS
     *
     */
    public function setMaxTimeToProcessJobs(int $maxTimeToProcessJobs): self
    {
        $this->maxTimeToProcessJobs = $maxTimeToProcessJobs;

        return $this;
    }

    /**
     * Get the max allowed run time to process jobs in SECONDS
     *
     */
    public function getMaxTimeToProcessJobs(): ?int
    {
        return $this->maxTimeToProcessJobs;
    }

    /**
     * Set the max consumable memory constrain when processing jobs
     *
     */
    public function withMaxMemoryConstrain(): self
    {
        $this->hasMaxMemoryConstrain = true;

        if (! $this->getMaxMemoryToConsumed()) {
            $this->setMaxMemoryToConsumed($this->deduceSafeMaxAllowedMemory());
        }

        return $this;
    }

    /**
     * Set the max allowed consumable memory to process jobs in BYTES
     *
     */
    public function setMaxMemoryToConsumed(int $maxMemoryToConsumed): self
    {
        $this->maxMemoryToConsumed = $maxMemoryToConsumed;

        return $this;
    }

    /**
     * get the max allowed consumable memory to process jobs in BYTES
     *
     */
    public function getMaxMemoryToConsumed(): ?int
    {
        return $this->maxMemoryToConsumed;
    }

    /**
     * Set constrain to estimate next job possible processing time to allow next job to be processed/run
     *
     */
    public function withEstimatedTimeToProcessNextJobConstrain(): self
    {
        $this->hasEstimatedTimeToProcessNextJobConstrain = true;

        return $this;
    }

    /**
     * Check if the job runner is running in context-aware mode
     */
    public function isRunningInContextAwareMode(): bool
    {
        return $this->runInContextAwareMode;
    }

    /**
     * Disable context-aware constraints
     */
    public function withDisableContextAwareConstraints(): self
    {
        $this->runInContextAwareMode = false;

        return $this;
    }

    /**
     * Set the current context id
     */
    public function setCurrentContextId(?int $contextId): self
    {
        $this->currentContextId = $contextId;

        return $this;
    }

    /**
     * Get the current context id
     */
    public function getCurrentContextId(): ?int
    {
        return $this->currentContextId;
    }

    /**
     * Process/Run/Execute jobs off CLI
     */
    public function processJobs(?EloquentBuilder $jobBuilder = null): bool
    {
        // if job is already processing via job runner, will not start to process more
        if ($this->isJobProcessing()) {
            return false;
        }

        try {

            $jobBuilder ??= $this->jobQueue->getJobModelBuilder();

            if ($this->isRunningInContextAwareMode()) {
                $queueHandler = app()->get(\Illuminate\Contracts\Queue\Queue::class);
                if ($queueHandler instanceof \PKP\queue\DatabaseQueue) {
                    $queueHandler
                        ->enableJobInContextAwareMode()
                        ->setContextId($this->getCurrentContextId());

                    $jobBuilder = $this->jobQueue->applyJobContextAwareFilter(
                        $jobBuilder,
                        $this->getCurrentContextId()
                    );
                }
            }


            // return back if there is no job to process
            if (!$jobBuilder->count()) {
                return false;
            }

            // Check for stale lock and clear it up if available
            $lockData = Cache::get($this->getCacheKey());
            if ($lockData && (time() - $lockData['timestamp']) >= $this->getCacheTimeout()) {
                Cache::forget($this->getCacheKey());
            }

            // Try to acquire lock by setting cache key
            $newToken = Str::uuid()->toString();
            $newLockData = ['timestamp' => time(), 'token' => $newToken];
            if (!Cache::add($this->getCacheKey(), $newLockData, $this->getCacheTimeout())) {
                // Re-check cache to avoid race condition
                // Store result to avoid double-call and properly handle NULL case
                $cachedLock = Cache::get($this->getCacheKey());
                if ($cachedLock && $cachedLock['token'] !== $newToken) {
                    // JobRunner cache lock acquired by another process
                    // will consider as processing job so will not proceed
                    return false;
                }

                // If Cache::get() returned NULL, the lock may have been cleared between
                // add() and get(). Add a small delay and re-check to be safe.
                if (!$cachedLock) {
                    usleep(10000); // 10ms delay
                    $cachedLock = Cache::get($this->getCacheKey());
                    if ($cachedLock && $cachedLock['token'] !== $newToken) {
                        return false;
                    }
                }
            }

            // force flush the output buffer
            app()->flushOutputBuffer();

            $this->jobProcessing = true; // set the job runner to processing state
            $this->jobProcessedOnRunner = 0;
            $jobProcessingStartTime = time();

            while ($jobBuilder->count()) {
                if ($this->exceededJobLimit($this->jobProcessedOnRunner)) {
                    return true;
                }

                if ($this->exceededTimeLimit($jobProcessingStartTime)) {
                    return true;
                }

                if ($this->exceededMemoryLimit()) {
                    return true;
                }

                if ($this->mayExceedMemoryLimitAtNextJob($this->jobProcessedOnRunner, $jobProcessingStartTime)) {
                    return true;
                }

                // if there is no more jobs to run, exit the loop
                if ($this->jobQueue->runJobInQueue($jobBuilder) === false) {
                    return true;
                }

                $this->jobProcessedOnRunner = $this->jobProcessedOnRunner + 1;
            }

            return true;

        } finally {
            Cache::forget($this->getCacheKey());
            $this->jobProcessing = false; // reset the job processing state
        }
    }

    /**
     * Get the number of job successfully processed on job runner
     */
    public function getJobProcessedCount(): int
    {
        return $this->jobProcessedOnRunner;
    }

    /**
     * Get the current status of job runner to see if this is processing jobs.
     * It will check for both in the current request life cycle and also
     * in the other request life cycle.
     */
    public function isJobProcessing(): bool
    {
        // Job is being processed within the current reqeust life cycle
        if ($this->jobProcessing) {
            return true;
        }

        // if not processing within current request life cycle,
        // we will check if it's being processed in other request life cycle
        $lockData = Cache::get($this->getCacheKey());

        // no cache lock found, job is not being processed
        if (!$lockData) {
            return false;
        }

        if ((time() - $lockData['timestamp']) < $this->getCacheTimeout()) {
            // JobRunner is locked by another process (cache key exists), will consider as processing
            return true;
        }

        // Stale lock detected, will consider that the job is not being processed
        return false;
    }

    /**
     * Get the cache key for the job runner
     */
    public function getCacheKey(): string
    {
        return 'jobRunnerLastRun';
    }

    /**
     * Get the cache timeout(expiry time) for the job runner cache
     */
    public function getCacheTimeout(): int
    {
        // To ensure long running jobs have enough time to complete, we double the max execution time
        return 2 * $this->deduceSafeMaxExecutionTime();
    }

    /**
     * Check if max job has processed or not
     *
     * @param int $jobProcessedCount    The number of jobs that has processed so far
     */
    protected function exceededJobLimit(int $jobProcessedCount): bool
    {
        if (! $this->hasMaxJobsConstrain) {
            return false;
        }

        return $jobProcessedCount >= $this->getMaxJobsToProcess();
    }

    /**
     * Check if max run time to process jobs has exceed or not
     *
     * @param int $jobProcessingStartTime   The start time since job processing has started in seconds
     */
    protected function exceededTimeLimit(int $jobProcessingStartTime): bool
    {
        if (! $this->hasMaxExecutionTimeConstrain) {
            return false;
        }

        return (time() - $jobProcessingStartTime) >= $this->getMaxTimeToProcessJobs();
    }

    /**
     * Check if memory consumed since job processing started has exceed defined max memory
     */
    protected function exceededMemoryLimit(): bool
    {
        if (! $this->hasMaxMemoryConstrain) {
            return false;
        }

        return memory_get_usage(true) >= $this->getMaxMemoryToConsumed();
    }

    /**
     * Estimate if next job processing time will likely exceed defined max processing run time
     *
     * @param int $jobProcessedCount        The number of jobs that has processed so far
     * @param int $jobProcessingStartTime   The start time since job processing has started in seconds
     */
    protected function mayExceedMemoryLimitAtNextJob(int $jobProcessedCount, int $jobProcessingStartTime): bool
    {
        if (! $this->hasEstimatedTimeToProcessNextJobConstrain) {
            return false;
        }

        // if no max run time constrain set, no point in estimating the likely time exceed
        if (!$this->hasMaxExecutionTimeConstrain) {
            return false;
        }

        // if no job has processed yet, no way to calculate next job possible processing time
        if ($jobProcessedCount <= 0) {
            return false;
        }

        $currentTotalExecutionTime = time() - $jobProcessingStartTime;
        $timePerJob = (int)($currentTotalExecutionTime / $jobProcessedCount);
        $totalTimeByNextJobComplete = $currentTotalExecutionTime + ($timePerJob * 3);

        return $totalTimeByNextJobComplete > $this->getMaxTimeToProcessJobs();
    }

    /**
     * Deduce possible safe max job processing run time in SECONDS
     *
     * It will consider both what defined in the ini file and application config file
     * and will take a minimum one based on those two values
     */
    protected function deduceSafeMaxExecutionTime(): int
    {
        $maxExecutionTimeSetToINI = (int)ini_get('max_execution_time');
        $maxExecutionTimeSetToConfig = (int)Config::getVar('queues', 'job_runner_max_execution_time', 20);

        return $maxExecutionTimeSetToINI <= 0
            ? $maxExecutionTimeSetToConfig
            : min($maxExecutionTimeSetToINI, $maxExecutionTimeSetToConfig);
    }

    /**
     * Deduce possible safe max consumable memory for job processing in BYTES
     *
     * It will consider both of what defined in the ini file and the application config file
     * and will take a minimum one based on those two values
     *
     * In the application config file, max memory can be defined as INT or STRING value in following manner
     *
     *      If defined as INT (e.g 90), it will be calculated as percentage value of
     *      what currently defined in the ini file.
     *
     *      If defined as STRING (e.g 128M), it will try to calculate it as memory defined in megabytes
     *      but if failed, will try to cast to INT to apply percentage rule
     */
    protected function deduceSafeMaxAllowedMemory(): int
    {
        $maxMemoryLimitSetToINI = function_exists('ini_get') ? ini_get('memory_limit') : '128M';

        if (!$maxMemoryLimitSetToINI || -1 === $maxMemoryLimitSetToINI || '-1' === $maxMemoryLimitSetToINI) {
            $maxMemoryLimitSetToINI = '128M'; // Unlimited, set to 128M.
        }

        $maxMemoryLimitSetToINIInBytes = convertHrToBytes($maxMemoryLimitSetToINI);

        $maxMemoryLimitSetToConfig = Config::getVar('queues', 'job_runner_max_memory', 80);

        $maxMemoryLimitSetToConfigInBytes = in_array(strtolower(substr((string)$maxMemoryLimitSetToConfig, -1)), ['k', 'm', 'g'], true)
            ? convertHrToBytes($maxMemoryLimitSetToConfig)
            : (int)($maxMemoryLimitSetToINIInBytes * ((float)((int)$maxMemoryLimitSetToConfig / 100)));

        return min($maxMemoryLimitSetToConfigInBytes, $maxMemoryLimitSetToINIInBytes);
    }
}
