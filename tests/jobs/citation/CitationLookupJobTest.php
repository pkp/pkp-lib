<?php

/**
 * @file tests/jobs/citation/CitationLookupJobTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for CitationLookupJob's bounded, chain-safe retry handling.
 */

namespace PKP\tests\jobs\citation;

use Exception;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\CitationLookupJob;
use PKP\tests\PKPTestCase;
use ReflectionMethod;
use ReflectionProperty;

#[RunTestsInSeparateProcesses]
#[CoversClass(CitationLookupJob::class)]
class CitationLookupJobTest extends PKPTestCase
{
    private function invoke(CitationLookupJob $job, string $method, array $args): mixed
    {
        $reflected = new ReflectionMethod($job, $method);
        $reflected->setAccessible(true);
        return $reflected->invoke($job, ...$args);
    }

    private function read(object $object, string $property): mixed
    {
        $reflected = new ReflectionProperty($object, $property);
        $reflected->setAccessible(true);
        return $reflected->getValue($object);
    }

    /** A 5xx/timeout while the budget remains: a fresh, delayed copy goes on the front of the chain. */
    public function testServiceErrorPrependsADelayedRetryWhileBudgetRemains(): void
    {
        $job = new FakeCitationLookupJob(1, 2, 'contact@example.org');

        $this->invoke($job, 'retryAfterServiceError', [500]);

        $chained = $this->read($job, 'chained');
        $this->assertCount(1, $chained);

        $retry = unserialize($chained[0]);
        $this->assertInstanceOf(FakeCitationLookupJob::class, $retry);
        $this->assertSame(1, $this->read($retry, 'serviceRetries'));
        $this->assertNotNull($retry->delay);
    }

    /** A 5xx/timeout once the retry budget is spent: fail the job, so the chain stops instead of advancing. */
    public function testServiceErrorFailsTheJobOnceRetryBudgetIsSpent(): void
    {
        $failedWith = null;

        $queueJob = Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('fail')->once()->andReturnUsing(function ($exception) use (&$failedWith) {
            $failedWith = $exception;
        });

        $job = new FakeCitationLookupJob(1, 2, 'contact@example.org', FakeCitationLookupJob::MAX_SERVICE_RETRIES);
        $job->setJob($queueJob);

        $this->invoke($job, 'retryAfterServiceError', [500]);

        $this->assertEmpty($this->read($job, 'chained'));
        $this->assertInstanceOf(Exception::class, $failedWith);
        $this->assertStringContainsString('HTTP 500', $failedWith->getMessage());
    }

    /** A 429/503 releases for Retry-After + 3s (or 60s if absent), plus up to RELEASE_JITTER_SECONDS. */
    public function testRateLimitReleasesForRetryAfter(): void
    {
        $jitter = FakeCitationLookupJob::RELEASE_JITTER_SECONDS;

        $withHeader = $this->releaseDelayFor(retryAfter: 10);
        $this->assertGreaterThanOrEqual(13, $withHeader);
        $this->assertLessThanOrEqual(13 + $jitter, $withHeader);

        $withoutHeader = $this->releaseDelayFor(retryAfter: null);
        $this->assertGreaterThanOrEqual(60, $withoutHeader);
        $this->assertLessThanOrEqual(60 + $jitter, $withoutHeader);
    }

    /** @return int|null The delay retryAfterRateLimit() released with. */
    private function releaseDelayFor(?int $retryAfter): ?int
    {
        $released = null;

        $queueJob = Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->andReturnUsing(function ($delay) use (&$released) {
            $released = $delay;
        });

        $job = new FakeCitationLookupJob(1, 2, 'contact@example.org');
        $job->setJob($queueJob);
        $this->invoke($job, 'retryAfterRateLimit', [$retryAfter, 429]);

        return $released;
    }
}

/** Minimal concrete subclass, so the base class's retry helpers can be exercised on their own. */
class FakeCitationLookupJob extends CitationLookupJob
{
    public function __construct(int $contextId, int $citationId, string $contactEmail, int $serviceRetries = 0)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
        $this->contactEmail = $contactEmail;
        $this->serviceRetries = $serviceRetries;
    }

    public function handle(): void
    {
    }
}
