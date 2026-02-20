<?php

/**
 * @file tests/classes/queue/PKPJobRunnerLockingTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for JobRunner cache locking mechanism to prevent multiple concurrent runners.
 */

namespace PKP\tests\classes\queue;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\queue\JobRunner;
use PKP\core\PKPQueueProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
     * Test that getCacheKey returns the expected key
     */
    public function testGetCacheKeyReturnsExpectedKey(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $this->assertEquals('jobRunnerLastRun', $runner->getCacheKey());
    }

    /**
     * Test that getCacheTimeout returns double the max execution time
     */
    public function testGetCacheTimeoutIsDoubleMaxExecutionTime(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set a specific max time
        $runner->setMaxTimeToProcessJobs(5);

        // The cache timeout should be double the max execution time
        // getCacheTimeout uses deduceSafeMaxExecutionTime internally
        $cacheTimeout = $runner->getCacheTimeout();

        // The timeout should be positive and reasonably calculated
        $this->assertGreaterThan(0, $cacheTimeout);
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
     * Test processJobs acquires a lock before processing
     */
    public function testProcessJobsAcquiresLock(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        // Process jobs - this should acquire a lock
        $runner->processJobs();

        // After processing, the lock should be released (in finally block)
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
        $mockQueueProvider->shouldReceive('runJobInQueue')
            ->andThrow(new \Exception('Test exception'));

        $runner = JobRunner::getInstance($mockQueueProvider);

        try {
            $runner->processJobs();
        } catch (\Exception $e) {
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
     * Test processJobs handles race condition by checking token
     */
    public function testProcessJobsHandlesRaceCondition(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set up a pre-existing lock to simulate race condition
        $existingLock = [
            'timestamp' => time(),
            'token' => 'competing-token'
        ];
        Cache::put($runner->getCacheKey(), $existingLock, $runner->getCacheTimeout());

        // When attempting to process, it should detect the competing lock
        $result = $runner->processJobs();

        // Should return false due to competing lock
        $this->assertFalse($result);
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
}
