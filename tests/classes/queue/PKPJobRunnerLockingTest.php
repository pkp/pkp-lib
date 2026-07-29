<?php

/**
 * @file tests/classes/queue/PKPJobRunnerLockingTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for JobRunner cache locking mechanism to prevent multiple concurrent runners.
 */

namespace PKP\tests\classes\queue;

use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\config\Config;
use PKP\core\PKPQueueProvider;
use PKP\queue\JobRunner;
use PKP\tests\PKPTestCase;
use ReflectionClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(JobRunner::class)]
class PKPJobRunnerLockingTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset the singleton instance for test isolation
        JobRunner::resetInstance();

        // Clear any existing cache locks
        Cache::forget('jobRunnerLastRun');
    }

    protected function tearDown(): void
    {
        // Clear cache locks after test
        Cache::forget('jobRunnerLastRun');

        // Reset singleton
        JobRunner::resetInstance();

        Mockery::close();

        parent::tearDown();
    }

    /**
     * Create a mock PKPQueueProvider
     */
    protected function createMockQueueProvider(): PKPQueueProvider
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')
            ->andReturn($mockBuilder);

        return $mockQueueProvider;
    }

    /**
     * Create a mock PKPQueueProvider with jobs available
     */
    protected function createMockQueueProviderWithJobs(): PKPQueueProvider
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')
            ->andReturn(1, 0); // First call returns 1 (has jobs), second returns 0 (no more jobs)

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')
            ->andReturn(true);

        return $mockQueueProvider;
    }

    /**
     * Test that getCacheKey returns the expected key
     */
    public function testGetCacheKeyReturnsExpectedKey(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $this->assertEquals('jobRunnerLastRun', $runner->getCacheKey());
    }

    /**
     * Test that getCacheTimeout returns exactly double the safe max execution
     * time which derived from deduceSafeMaxExecutionTime()
     */
    public function testGetCacheTimeoutIsDoubleMaxExecutionTime(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('deduceSafeMaxExecutionTime');
        $method->setAccessible(true);
        $safeMaxExecutionTime = $method->invoke($runner);

        $this->assertGreaterThan(0, $safeMaxExecutionTime);
        $this->assertSame(2 * $safeMaxExecutionTime, $runner->getCacheTimeout());
    }

    /**
     * Test isJobProcessing returns false when no lock exists
     */
    public function testIsJobProcessingReturnsFalseWhenNoLock(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Ensure no cache lock exists
        Cache::forget($runner->getCacheKey());

        $this->assertFalse($runner->isJobProcessing());
    }

    /**
     * Test isJobProcessing returns true when fresh lock exists
     */
    public function testIsJobProcessingReturnsTrueWhenFreshLock(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set a fresh lock
        $lockData = [
            'timestamp' => time(),
            'token' => 'test-token-123'
        ];
        Cache::put($runner->getCacheKey(), $lockData, $runner->getCacheTimeout());

        $this->assertTrue($runner->isJobProcessing());
    }

    /**
     * Test isJobProcessing returns false when lock is stale
     */
    public function testIsJobProcessingReturnsFalseWhenStaleLock(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set a stale lock (timestamp older than cache timeout)
        $lockData = [
            'timestamp' => time() - ($runner->getCacheTimeout() + 100),
            'token' => 'old-test-token'
        ];
        Cache::put($runner->getCacheKey(), $lockData, 3600);

        $this->assertFalse($runner->isJobProcessing());
    }

    /**
     * Test processJobs holds the cache lock WHILE processing a job, then
     * releases it afterwards.
     */
    public function testProcessJobsHoldsLockDuringProcessingAndReleasesAfter(): void
    {
        $lockDuringProcessing = null;

        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(1);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);

        // Capture the lock state at the exact moment a job is being processed,
        // then return false to end the processing loop.
        $mockQueueProvider->shouldReceive('runJobInQueue')
            ->andReturnUsing(function () use (&$lockDuringProcessing, $runner) {
                $lockDuringProcessing = Cache::get($runner->getCacheKey());
                return false;
            });

        $runner->processJobs();

        // A lock (with a token) was held during processing...
        $this->assertIsArray($lockDuringProcessing);
        $this->assertArrayHasKey('token', $lockDuringProcessing);

        // ...and released afterwards (finally block).
        $this->assertNull(Cache::get($runner->getCacheKey()));
        $this->assertFalse($runner->isJobProcessing());
    }

    /**
     * Test processJobs releases lock on success
     */
    public function testProcessJobsReleasesLockOnSuccess(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        $runner->processJobs();

        // Lock should be released after successful processing
        $lockData = Cache::get($runner->getCacheKey());
        $this->assertNull($lockData);
    }

    /**
     * Test processJobs releases lock even on exception
     */
    public function testProcessJobsReleasesLockOnException(): void
    {
        // Create a mock that throws an exception when running jobs
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')
            ->andReturn(1, 0); // First call returns 1, second returns 0

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')
            ->andThrow(new Exception('Test exception'));

        $runner = JobRunner::getInstance($mockQueueProvider);

        try {
            $runner->processJobs();
        } catch (Exception $e) {
            // Expected exception
        }

        // Lock should still be released even after exception
        $lockData = Cache::get($runner->getCacheKey());
        $this->assertNull($lockData);
    }

    /**
     * Test processJobs returns false when lock is already held
     */
    public function testProcessJobsReturnsFalseWhenLockAlreadyHeld(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Simulate another process holding the lock
        $lockData = [
            'timestamp' => time(),
            'token' => 'other-process-token'
        ];
        Cache::put($runner->getCacheKey(), $lockData, $runner->getCacheTimeout());

        // processJobs should return false since lock is held
        $result = $runner->processJobs();

        $this->assertFalse($result);
    }

    /**
     * Test the Cache::add() race re-check branch: the lock appears free at the
     * initial isJobProcessing() check, but a competing process wins the
     * Cache::add() race, so the follow-up read sees a different token and
     * processJobs() bails out without processing.
     *
     * This exercises the inner branch that the "lock already held" test cannot
     * reach (that one short-circuits earlier in isJobProcessing()). We drive it
     * by mocking the Cache facade so add() fails and the re-read returns a
     * foreign token.
     */
    public function testProcessJobsBailsWhenAddRaceLostToDifferentToken(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(1);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        // Must never run a job: we bail before the processing loop.
        $mockQueueProvider->shouldReceive('runJobInQueue')->never();

        $runner = JobRunner::getInstance($mockQueueProvider);

        // Cache::get() sequence: (1) isJobProcessing → no lock, (2) stale-lock
        // pre-check → no lock, (3) post-add re-check → competing lock.
        Cache::shouldReceive('get')
            ->andReturn(null, null, ['timestamp' => time(), 'token' => 'competing-token']);
        // The atomic add loses the race.
        Cache::shouldReceive('add')->andReturn(false);
        // Allow teardown's Cache::forget() against the mocked facade.
        Cache::shouldReceive('forget')->andReturnTrue();

        $result = $runner->processJobs();

        $this->assertFalse($result);
    }

    /**
     * Test processJobs returns false and acquires no lock when the queue is
     * empty (count() === 0), bailing before any lock work.
     */
    public function testProcessJobsReturnsFalseWhenQueueEmpty(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider(); // count() === 0
        $runner = JobRunner::getInstance($mockQueueProvider);

        $result = $runner->processJobs();

        $this->assertFalse($result);
        $this->assertSame(0, $runner->getJobProcessedCount());
        $this->assertNull(Cache::get($runner->getCacheKey()));
    }

    /**
     * Test the processing loop stops once the max-jobs limit is reached, even
     * when the queue still reports available jobs.
     */
    public function testProcessJobsStopsAtMaxJobsLimit(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(5); // always has jobs

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setMaxJobsToProcess(2)->withMaxJobsConstrain();

        $result = $runner->processJobs();

        $this->assertTrue($result);
        $this->assertSame(2, $runner->getJobProcessedCount());
        // Lock released afterwards.
        $this->assertNull(Cache::get($runner->getCacheKey()));
    }

    /**
     * Test isJobProcessing returns true based on in-request state alone, even
     * with no cross-request cache lock present.
     */
    public function testIsJobProcessingReturnsTrueWhenProcessingInCurrentRequest(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        Cache::forget($runner->getCacheKey());

        $reflection = new ReflectionClass($runner);
        $property = $reflection->getProperty('jobProcessing');
        $property->setAccessible(true);
        $property->setValue($runner, true);

        $this->assertTrue($runner->isJobProcessing());
    }

    /**
     * Test that when the cross-request lock is disabled via config, a present
     * cache lock is ignored by isJobProcessing() (DB row locking still guards
     * duplicate execution).
     */
    public function testCrossRequestLockDisabledIgnoresCacheLock(): void
    {
        $data = & Config::getData();
        $original = $data['queues']['job_runner_cross_request_lock'] ?? true;
        $data['queues']['job_runner_cross_request_lock'] = false;

        try {
            $runner = JobRunner::getInstance($this->createMockQueueProvider());

            $this->assertFalse($runner->isCrossRequestLockEnabled());

            // A fresh cache lock exists, but should be ignored.
            Cache::put(
                $runner->getCacheKey(),
                ['timestamp' => time(), 'token' => 'some-token'],
                3600
            );

            $this->assertFalse($runner->isJobProcessing());
        } finally {
            $data['queues']['job_runner_cross_request_lock'] = $original;
        }
    }
}
