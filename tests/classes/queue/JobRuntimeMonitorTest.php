<?php

/**
 * @file tests/classes/queue/JobRuntimeMonitorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the runtime thresholds that decide when a queue job's duration
 *        is reported against the connection's configured retry_after
 */

namespace PKP\tests\classes\queue;

use Illuminate\Contracts\Queue\Job as JobContract;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\queue\JobRuntimeMonitor;
use PKP\tests\PKPTestCase;

#[CoversClass(JobRuntimeMonitor::class)]
class JobRuntimeMonitorTest extends PKPTestCase
{
    protected JobRuntimeMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->monitor = new JobRuntimeMonitor();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * A monitor that captures its reports instead of writing them to the error log,
     * and reads retry_after from a fixed value instead of the container config.
     */
    protected function recordingMonitor(?int $retryAfter): object
    {
        return new class ($retryAfter) extends JobRuntimeMonitor {
            public array $reported = [];

            public function __construct(protected ?int $configuredRetryAfter)
            {
            }

            protected function retryAfter(string $connectionName): ?int
            {
                return $this->configuredRetryAfter;
            }

            protected function report(string $message): void
            {
                $this->reported[] = $message;
            }

            public function callStart(JobContract $job): void
            {
                $this->start($job);
            }

            public function callFinish(JobContract $job): void
            {
                $this->finish('database', $job);
            }

            public function callDiscard(JobContract $job): void
            {
                $this->discard($job);
            }

            public function tracked(): array
            {
                return $this->startedAt;
            }
        };
    }

    /**
     * A queue job stub with the small surface the monitor touches.
     */
    protected function job(string $id = '1', ?int $timeout = null): JobContract
    {
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn($id);
        $job->shouldReceive('resolveName')->andReturn('TestJob');
        $job->shouldReceive('getQueue')->andReturn('queue');
        $job->shouldReceive('timeout')->andReturn($timeout);

        return $job;
    }

    /**
     * Build a report for a runtime measured against a retry_after.
     */
    protected function message(float $seconds, ?int $retryAfter): ?string
    {
        return $this->monitor->messageForRuntime('TestJob', 'queue', $seconds, $retryAfter);
    }

    public function testAnOrdinaryRuntimeIsNotReported()
    {
        // Well inside the limit: nothing worth telling the administrator about
        $this->assertNull($this->message(12.0, 610));
    }

    public function testAConnectionWithoutRetryAfterIsNotReported()
    {
        // The sync connection never re-reserves a row, so there is nothing to compare
        $this->assertNull($this->message(9999.0, null));
        $this->assertNull($this->message(9999.0, 0));
    }

    public function testARuntimeApproachingRetryAfterIsReported()
    {
        // 80% of the limit is the point at which the warning becomes useful, while
        // there is still headroom to act
        $this->assertNull($this->message(79.0, 100));

        $message = $this->message(80.0, 100);
        $this->assertNotNull($message);
        $this->assertStringContainsString('TestJob', $message);
        $this->assertStringContainsString('80% of the configured [queues] retry_after of 100 seconds', $message);
        $this->assertStringNotContainsString('exceeding', $message);
    }

    public function testARuntimeExceedingRetryAfterIsReportedAsSuch()
    {
        $message = $this->message(1842.0, 610);
        $this->assertNotNull($message);
        $this->assertStringContainsString('ran for 1,842.0 seconds', $message);
        $this->assertStringContainsString('exceeding the configured [queues] retry_after of 610 seconds', $message);
        // The consequence is the part an administrator cannot infer on their own
        $this->assertStringContainsString('re-reservation', $message);
    }

    public function testTheReportSuggestsAUsableRetryAfter()
    {
        // A suggestion at or below the measured runtime would recreate the problem
        foreach ([80.0, 611.0, 1842.0, 7300.5] as $seconds) {
            $suggestion = $this->monitor->suggestedRetryAfter($seconds);
            $this->assertGreaterThan($seconds, $suggestion, "suggestion for {$seconds}s must exceed it");
            $this->assertSame(0, $suggestion % 10, 'suggestion should be a round number of seconds');
        }

        // Longer jobs never suggest a smaller value
        $this->assertGreaterThanOrEqual(
            $this->monitor->suggestedRetryAfter(600.0),
            $this->monitor->suggestedRetryAfter(1200.0)
        );

        $this->assertStringContainsString(
            'at least ' . $this->monitor->suggestedRetryAfter(1842.0) . ' seconds',
            (string) $this->message(1842.0, 610)
        );
    }

    public function testAJobIsOnlyMeasuredBetweenItsOwnStartAndFinish()
    {
        $monitor = $this->recordingMonitor(610);
        $job = $this->job();

        // Finishing a job that was never started must not report a bogus runtime
        $monitor->callFinish($job);
        $this->assertSame([], $monitor->reported);

        $monitor->callStart($job);
        $this->assertNotSame([], $monitor->tracked());

        $monitor->callFinish($job);
        // A runtime measured in microseconds is far below the threshold
        $this->assertSame([], $monitor->reported);
        // ... and the start time is released, so a daemon does not accumulate them
        $this->assertSame([], $monitor->tracked());
    }

    public function testAFailedJobReleasesItsStartTimeWithoutReporting()
    {
        // JobProcessed never fires for a failed job, so JobExceptionOccurred is what
        // keeps the map from growing without bound in a long-lived worker
        $monitor = $this->recordingMonitor(610);
        $job = $this->job();

        $monitor->callStart($job);
        $monitor->callDiscard($job);

        $this->assertSame([], $monitor->tracked());
        $this->assertSame([], $monitor->reported);
    }

    public function testRegisteringTheListenersDoesNotThrow()
    {
        // Guards the wiring in PKPQueueProvider::boot() against a bad facade or a
        // renamed queue event class
        $this->expectNotToPerformAssertions();

        (new JobRuntimeMonitor())->register();
    }
}
