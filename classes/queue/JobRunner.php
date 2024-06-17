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
use PKP\config\Config;
use PKP\core\PKPQueueProvider;

class JobRunner
{
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
     * Create a new instance
     *
     */
    public function __construct(?PKPQueueProvider $jobQueue = null)
    {
        $this->jobQueue = $jobQueue ?? app('pkpJobQueue');
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
     * Process/Run/Execute jobs off CLI
     *
     */
    public function processJobs(?EloquentBuilder $jobBuilder = null): bool
    {
        $jobBuilder ??= $this->jobQueue->getJobModelBuilder();

        $jobProcessedCount = 0;
        $jobProcessingStartTime = time();

        while ($jobBuilder->count()) {
            if ($this->exceededJobLimit($jobProcessedCount)) {
                return true;
            }

            if ($this->exceededTimeLimit($jobProcessingStartTime)) {
                return true;
            }

            if ($this->exceededMemoryLimit()) {
                return true;
            }

            if ($this->mayExceedMemoryLimitAtNextJob($jobProcessedCount, $jobProcessingStartTime)) {
                return true;
            }

            $this->jobQueue->runJobInQueue();

            $jobProcessedCount = $jobProcessedCount + 1;
        }

        return true;
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
     *
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
     *
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
     *
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
     *
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
