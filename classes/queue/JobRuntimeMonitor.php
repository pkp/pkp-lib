<?php

/**
 * @file classes/queue/JobRuntimeMonitor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobRuntimeMonitor
 *
 * @brief Measures how long queue jobs actually run and reports when a runtime
 *        approaches or exceeds the connection's configured retry_after.
 */

namespace PKP\queue;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class JobRuntimeMonitor
{
    /**
     * Fraction of retry_after at which a job is reported as approaching the limit.
     *
     * Reporting only once the limit has been passed means the first notice arrives
     * after the damage. This gives the warning while there is still headroom.
     */
    public const WARNING_THRESHOLD = 0.9;

    /**
     * Headroom applied to a measured runtime when suggesting a new retry_after.
     */
    public const SUGGESTION_HEADROOM = 1.25;

    /**
     * Prefix applied to every reported line, so the reports can be grepped out of a
     * busy error log.
     */
    public const LOG_PREFIX = '[JOB RUNTIME]';

    /**
     * Start times of the jobs currently being processed, keyed by queue job id.
     *
     * Jobs do not overlap within a worker process, but a failed job never raises
     * JobProcessed, so entries are also discarded on JobExceptionOccurred to keep
     * this from growing in a long-lived daemon.
     *
     * @var array<string, float>
     */
    protected array $startedAt = [];

    /**
     * Register the queue event listeners.
     */
    public function register(): void
    {
        Queue::before(fn (JobProcessing $event) => $this->start($event->job));
        Queue::after(fn (JobProcessed $event) => $this->finish($event->connectionName, $event->job));
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => $this->discard($event->job));

        // Laravel dispatches JobTimedOut immediately before the worker SIGKILLs
        // itself, so without a listener a timeout leaves no trace at all.
        Event::listen(JobTimedOut::class, fn (JobTimedOut $event) => $this->reportTimeout($event->connectionName, $event->job));
    }

    /**
     * Record when a job started.
     */
    protected function start(JobContract $job): void
    {
        $this->startedAt[$job->getJobId()] = microtime(true);
    }

    /**
     * Forget a job's start time without reporting on it.
     */
    protected function discard(JobContract $job): void
    {
        unset($this->startedAt[$job->getJobId()]);
    }

    /**
     * Measure a completed job and report if its runtime is worth reporting.
     */
    protected function finish(string $connectionName, JobContract $job): void
    {
        $jobId = $job->getJobId();

        if (!isset($this->startedAt[$jobId])) {
            return;
        }

        $seconds = microtime(true) - $this->startedAt[$jobId];
        unset($this->startedAt[$jobId]);

        $message = $this->messageForRuntime(
            $job->resolveName(),
            (string) $job->getQueue(),
            $seconds,
            $this->retryAfter($connectionName)
        );

        if ($message !== null) {
            $this->report($message);
        }
    }

    /**
     * Report a job the worker killed for exceeding its own timeout.
     */
    protected function reportTimeout(string $connectionName, JobContract $job): void
    {
        $this->discard($job);

        $retryAfter = $this->retryAfter($connectionName);

        $this->report(sprintf(
            '%s %s on queue "%s" was killed for exceeding its timeout of %d seconds. The worker process is being terminated and the job row stays reserved, so this attempt cannot be retried until retry_after (%s) has elapsed.',
            static::LOG_PREFIX,
            $job->resolveName(),
            $job->getQueue(),
            (int) ($job->timeout() ?? 0),
            $retryAfter ? $retryAfter . ' seconds' : 'not configured'
        ));
    }

    /**
     * Build the report for a measured runtime, or null when it is unremarkable.
     *
     * Kept free of side effects and framework state so the thresholds can be
     * tested directly.
     *
     * @param string   $name       Resolved job class name
     * @param string   $queue      Queue the job ran on
     * @param float    $seconds    Measured runtime
     * @param int|null $retryAfter The connection's configured retry_after, if any
     */
    public function messageForRuntime(string $name, string $queue, float $seconds, ?int $retryAfter): ?string
    {
        // Connections without a retry_after, such as sync, never re-reserve a row,
        // so there is nothing to compare against and nothing to warn about.
        if (!$retryAfter || $retryAfter <= 0) {
            return null;
        }

        if ($seconds < $retryAfter * static::WARNING_THRESHOLD) {
            return null;
        }

        $suggestion = $this->suggestedRetryAfter($seconds);

        if ($seconds > $retryAfter) {
            return sprintf(
                '%s %s on queue "%s" ran for %s seconds, exceeding the configured [queues] retry_after of %d seconds. Its queue row became eligible for re-reservation while the job was still running, so another worker or web request may have started the same job concurrently, which corrupts data for jobs that are not idempotent. Raise [queues] retry_after to at least %d seconds, or split the job into smaller batches.',
                static::LOG_PREFIX,
                $name,
                $queue,
                $this->formatSeconds($seconds),
                $retryAfter,
                $suggestion
            );
        }

        return sprintf(
            '%s %s on queue "%s" ran for %s seconds, which is %d%% of the configured [queues] retry_after of %d seconds. If it grows past that limit its queue row can be re-reserved while the job is still running. Consider raising [queues] retry_after to at least %d seconds.',
            static::LOG_PREFIX,
            $name,
            $queue,
            $this->formatSeconds($seconds),
            (int) round($seconds / $retryAfter * 100),
            $retryAfter,
            $suggestion
        );
    }

    /**
     * The retry_after that would comfortably cover the given runtime.
     */
    public function suggestedRetryAfter(float $seconds): int
    {
        // Round up to whole tens so the suggestion reads as a chosen value rather
        // than as one installation's measurement.
        return (int) (ceil($seconds * static::SUGGESTION_HEADROOM / 10) * 10);
    }

    /**
     * Get the configured retry_after for a queue connection.
     */
    protected function retryAfter(string $connectionName): ?int
    {
        $retryAfter = config("queue.connections.{$connectionName}.retry_after");

        return $retryAfter === null ? null : (int) $retryAfter;
    }

    /**
     * Format a runtime for display.
     */
    protected function formatSeconds(float $seconds): string
    {
        return number_format($seconds, 1);
    }

    /**
     * Write a report.
     *
     * Uses error_log() to match the existing queue failure reporting in
     * PKPQueueProvider::boot(); the Log facade channels are not available on this
     * branch.
     */
    protected function report(string $message): void
    {
        error_log($message);
    }
}
