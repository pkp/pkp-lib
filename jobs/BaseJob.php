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

use APP\facades\Repo;
use APP\core\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PKP\config\Config;
use PKP\submission\PKPSubmission;
use PKP\context\Context;
use ReflectionClass;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
     * Initialize the job
     */
    public function __construct()
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Config::getVar('queues', 'default_queue', 'queue');
    }

    /**
     * Determines if the job requires a context ID to operate correctly.
     * Override to return false for context-agnostic jobs.
     */
    public static function contextAware(): bool
    {
        return true;
    }

    /**
     * Determines if the job should attempt to deduce context from its properties.
     * Override to return false if context should only come from request/CLI.
     */
    public static function shouldTryToDeduceContextFromArgs(): bool
    {
        return true;
    }

    /**
     * Defines the properties to check for context deduction, in order of priority.
     */
    public static function contextDeductionArgMap(): array
    {
        return [
            'contextId',
            'context',
            'submissionId',
            'submission',
        ];
    }

    /**
     * Deduce and return the context ID from the job arguments.
     */
    public static function deduceContextIdFromJobArgs(ShouldQueue $job): ?int
    {
        $reflection = new ReflectionClass($job);
        $contextId = null;

        foreach (static::contextDeductionArgMap() as $argName) {
            if (!$reflection->hasProperty($argName)) {
                continue;
            }

            switch ($argName) {
                case 'contextId':
                    $property = $reflection->getProperty('contextId');
                    $contextIdValue = $property->getValue($job);
                    if (is_int($contextIdValue)) {
                        $contextId = $contextIdValue;
                    }
                    break;
                case 'context':
                    $property = $reflection->getProperty('context');
                    $contextValue = $property->getValue($job);

                    if ($contextValue instanceof Context) {
                        $contextId = $contextValue->getId();
                    }
                    break;
                case 'submissionId':
                    $property = $reflection->getProperty('submissionId');
                    $submissionIdValue = $property->getValue($job);
                    if (is_int($submissionIdValue)) {
                        $contextId = $submissionIdValue;
                    }
                    break;
                case 'submission':
                    $property = $reflection->getProperty('submission');
                    $submissionValue = $property->getValue($job);
                    if ($submissionValue instanceof PKPSubmission) {
                        $contextId = $submissionValue->getData('contextId');
                    }
                    break;
                default:
                    $contextId = null;
            }

            if ($contextId !== null) {
                return $contextId;
            }
        }

        return $contextId;
    }

    /**
     * handle the queue job execution process
     */
    abstract public function handle();
}
