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
 * @brief Abstract class for Jobs
 */

namespace PKP\jobs;

use APP\facades\Repo;
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
    public bool $failOnTimeout = true;

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
     * Each property must have a corresponding method name mapped.
     */
    public static function contextDeductionArgMap(): array
    {
        return [
            'contextId' => 'deduceFromArgContextId',
            'context' => 'deduceFromArgContext',
            'submissionId' => 'deduceFromArgSubmissionId',
            'submission' => 'deduceFromArgSubmission',
        ];
    }

    public static function deduceFromArgContextId(ShouldQueue $job): ?int
    {
        $reflection = new ReflectionClass($job);

        if (!$reflection->hasProperty('contextId')) {
            return null;
        }

        $property = $reflection->getProperty('contextId');
        $contextIdValue = $property->getValue($job);

        return is_int($contextIdValue) ? $contextIdValue : null;
    }

    public static function deduceFromArgContext(ShouldQueue $job): ?int
    {
        $reflection = new ReflectionClass($job);

        if (!$reflection->hasProperty('context')) {
            return null;
        }

        $property = $reflection->getProperty('context');
        $contextValue = $property->getValue($job);

        if ($contextValue instanceof Context) {
            return $contextValue->getId();
        }

        return null;
    }

    public static function deduceFromArgSubmissionId(ShouldQueue $job): ?int
    {
        $reflection = new ReflectionClass($job);

        if (!$reflection->hasProperty('submissionId')) {
            return null;
        }

        $property = $reflection->getProperty('submissionId');
        $submissionIdValue = $property->getValue($job);

        if (!is_int($submissionIdValue)) {
            return null;
        }

        return Repo::submission()->get($submissionIdValue)?->getData('contextId');
    }

    public static function deduceFromArgSubmission(ShouldQueue $job): ?int
    {
        $reflection = new ReflectionClass($job);

        if (!$reflection->hasProperty('submission')) {
            return null;
        }

        $property = $reflection->getProperty('submission');
        $submissionValue = $property->getValue($job);

        if ($submissionValue instanceof PKPSubmission) {
            return $submissionValue->getData('contextId');
        }

        return null;
    }

    /**
     * handle the queue job execution process
     */
    abstract public function handle();
}
