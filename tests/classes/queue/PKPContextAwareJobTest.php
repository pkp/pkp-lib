<?php

/**
 * @file tests/classes/queue/PKPContextAwareJobTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for ContextAwareJob interface and context-aware job processing.
 */

namespace PKP\tests\classes\queue;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\queue\JobRunner;
use PKP\queue\ContextAwareJob;
use PKP\core\PKPQueueProvider;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\TestJobSuccess;
use PKP\jobs\testJobs\TestJobContextAware;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[CoversClass(JobRunner::class)]
class PKPContextAwareJobTest extends PKPTestCase
{
    protected $busInstance;
    protected $queueInstance;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original facade instances
        $this->busInstance = Bus::getFacadeRoot();
        $this->queueInstance = Queue::getFacadeRoot();

        // Reset the singleton instance for test isolation
        JobRunner::resetInstance();
    }

    protected function tearDown(): void
    {
        // Restore original facade instances
        Bus::swap($this->busInstance);
        Queue::swap($this->queueInstance);

        // Reset singleton
        JobRunner::resetInstance();

        // Clean up test jobs
        PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->delete();

        Mockery::close();

        parent::tearDown();
    }

    // ========================================
    // 5a. Interface & Payload Tests
    // ========================================

    /**
     * Test payload includes context_id for ContextAwareJob
     */
    public function testPayloadIncludesContextIdForContextAwareJob(): void
    {
        $job = new TestJobContextAware(1);

        $this->assertInstanceOf(ContextAwareJob::class, $job);
        $this->assertEquals(1, $job->getContextId());
    }

    /**
     * Test payload excludes context_id for non-ContextAwareJob
     */
    public function testPayloadExcludesContextIdForNonContextAwareJob(): void
    {
        $job = new TestJobSuccess();

        $this->assertNotInstanceOf(ContextAwareJob::class, $job);
    }

    /**
     * Test context-aware job throws TypeError when context data is missing
     */
    public function testContextAwareJobThrowsTypeErrorWhenContextMissing(): void
    {
        $job = new TestJobContextAware(null);

        $this->assertInstanceOf(ContextAwareJob::class, $job);

        $this->expectException(\TypeError::class);
        $job->getContextId();
    }

    /**
     * Test context-aware job with explicit context ID
     */
    public function testContextAwareJobWithExplicitContextId(): void
    {
        $contextId = 42;
        $job = new TestJobContextAware($contextId);

        $this->assertEquals($contextId, $job->getContextId());
    }

    /**
     * Test context-aware job can derive context from Context object
     */
    public function testContextAwareJobWithDerivedContextId(): void
    {
        $mockContext = Mockery::mock(\PKP\context\Context::class);
        $mockContext->shouldReceive('getId')->andReturn(99);

        $job = new TestJobContextAware(null, $mockContext);

        $this->assertEquals(99, $job->getContextId());
    }

    // ========================================
    // 5b. Context Environment Tests (Web Request Mode)
    // ========================================

    /**
     * Test context-aware job runs in matching context environment
     */
    public function testContextAwareJobRunsInMatchingContextEnv(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderForContext(1);
        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(1);

        // Should process jobs for context 1
        $result = $runner->processJobs();

        $this->assertTrue($result);
    }

    /**
     * Test context-aware job skipped in different context environment
     */
    public function testContextAwareJobSkippedInDifferentContextEnv(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0); // No matching jobs

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(2); // Different context

        $result = $runner->processJobs();

        // Should return false because no matching jobs for context 2
        $this->assertFalse($result);
    }

    /**
     * Test context-aware job skipped when no context (site-level request)
     */
    public function testContextAwareJobSkippedInNoContextEnv(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(null); // No context

        $result = $runner->processJobs();

        $this->assertFalse($result);
    }

    /**
     * Test non-context-aware job runs in any context environment
     */
    public function testNonContextAwareJobRunsInAnyContextEnv(): void
    {
        $job = new TestJobSuccess();

        // Non-context-aware jobs don't implement the interface
        $this->assertNotInstanceOf(ContextAwareJob::class, $job);

        // They should be allowed to run regardless of context
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(1);
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        $result = $runner->processJobs();
        $this->assertTrue($result);
    }

    /**
     * Test non-context-aware job runs even when context-aware mode is enabled
     */
    public function testNonContextAwareJobRunsInContextAwareModeEnabled(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Enable context-aware mode
        $this->assertTrue($runner->isRunningInContextAwareMode());

        $runner->setCurrentContextId(1);
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        $result = $runner->processJobs();

        // Non-context-aware jobs should still run
        $this->assertTrue($result);
    }

    // ========================================
    // 5c. Context Filtering Tests (JobRunner)
    // ========================================

    /**
     * Test processJobs filters jobs by current context
     */
    public function testProcessJobsFiltersJobsByCurrentContext(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(1, 0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->with($mockBuilder, 1)
            ->once()
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(1);
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        $runner->processJobs();

        // applyJobContextAwareFilter should have been called with context 1
        $this->assertTrue(true); // Mockery will verify the expectation
    }

    /**
     * Test processJobs includes non-ContextAwareJob jobs when context-aware mode is disabled
     *
     * When context-aware filtering is disabled, all jobs should be processed
     * regardless of whether they implement ContextAwareJob or not.
     */
    public function testProcessJobsIncludesAllJobsWhenContextAwareDisabled(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        // count() is called: 1) initial check, 2) while loop entry, 3) while loop after job
        $mockBuilder->shouldReceive('count')->andReturn(1, 1, 0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->withDisableContextAwareConstraints(); // Disable context-aware mode
        $runner->setMaxJobsToProcess(1)->withMaxJobsConstrain();

        $result = $runner->processJobs();

        $this->assertTrue($result);
        $this->assertEquals(1, $runner->getJobProcessedCount());
    }

    /**
     * Test processJobs skips jobs from different context
     */
    public function testProcessJobsSkipsJobsFromDifferentContext(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0); // No matching jobs after filter

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(2);

        $result = $runner->processJobs();

        $this->assertFalse($result);
        $this->assertEquals(0, $runner->getJobProcessedCount());
    }

    /**
     * Test context-aware filter is applied to job builder
     */
    public function testContextAwareFilterAppliedToJobBuilder(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->once()
            ->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(1);

        $runner->processJobs();

        // Mockery verifies applyJobContextAwareFilter was called without any excaption
        // as the job runner is designed to by default run in context aware mode
        $this->assertTrue(true);
    }

    /**
     * Test context-aware mode can be disabled
     */
    public function testContextAwareModeCanBeDisabled(): void
    {
        $mockQueueProvider = $this->createMockQueueProviderWithJobs();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner->withDisableContextAwareConstraints();

        $this->assertFalse($runner->isRunningInContextAwareMode());
    }

    // ========================================
    // 5d. Multi-Context Isolation Tests
    // ========================================

    /**
     * Test context 1 job not processed in context 2 request
     */
    public function testContext1JobNotProcessedInContext2Request(): void
    {
        // Mock that context 2 request finds no matching jobs for context 1
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->with($mockBuilder, 2) // Context 2 filter
            ->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(2); // Running in context 2

        $result = $runner->processJobs();

        $this->assertFalse($result);
        $this->assertEquals(0, $runner->getJobProcessedCount());
    }

    /**
     * Test context 2 job not processed in context 1 request
     */
    public function testContext2JobNotProcessedInContext1Request(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->with($mockBuilder, 1) // Context 1 filter
            ->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(1); // Running in context 1

        $result = $runner->processJobs();

        $this->assertFalse($result);
    }

    /**
     * Test multiple jobs processed when context-aware mode is disabled
     */
    public function testMultipleJobsProcessedWhenContextAwareDisabled(): void
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        // count() is called: 1) initial check, 2) while loop entry, 3) after job 1, 4) after job 2
        $mockBuilder->shouldReceive('count')->andReturn(2, 2, 1, 0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true, true);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->withDisableContextAwareConstraints(); // Disable context-aware mode

        $result = $runner->processJobs();

        $this->assertTrue($result);
        $this->assertEquals(2, $runner->getJobProcessedCount());
    }

    /**
     * Test jobs remain in queue when context mismatches
     */
    public function testJobsRemainInQueueWhenContextMismatches(): void
    {
        // When filtering by context 2, no matching jobs found
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')->andReturn($mockBuilder);

        $runner = JobRunner::getInstance($mockQueueProvider);
        $runner->setCurrentContextId(2);

        $result = $runner->processJobs();

        // Jobs for other contexts remain unprocessed
        $this->assertFalse($result);
        $this->assertEquals(0, $runner->getJobProcessedCount());
    }

    /**
     * Create a mock PKPQueueProvider for a specific context
     */
    protected function createMockQueueProviderForContext(int $contextId): PKPQueueProvider
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(1, 0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')
            ->with($mockBuilder, $contextId)
            ->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true);

        return $mockQueueProvider;
    }

    /**
     * Create a mock PKPQueueProvider with jobs available
     */
    protected function createMockQueueProviderWithJobs(): PKPQueueProvider
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(1, 0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('applyJobContextAwareFilter')->andReturn($mockBuilder);
        $mockQueueProvider->shouldReceive('runJobInQueue')->andReturn(true);

        return $mockQueueProvider;
    }
}
