<?php

/**
 * @file tests/classes/queue/JobRunnerTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the JobRunner singleton, its processing constraints, and the
 *        Job availability scopes that drive the runner's stale reserved-job
 *        recovery
 */

namespace PKP\tests\classes\queue;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\PKPQueueProvider;
use PKP\job\models\Job;
use PKP\queue\JobRunner;
use PKP\tests\PKPTestCase;
use ReflectionClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(JobRunner::class)]
#[CoversClass(Job::class)]
class JobRunnerTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton for test isolation
        JobRunner::resetInstance();
    }

    protected function tearDown(): void
    {
        JobRunner::resetInstance();

        Mockery::close();

        parent::tearDown();
    }

    // ========================================
    // Singleton Tests
    // ========================================

    /**
     * Test getInstance returns same instance
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();

        $instance1 = JobRunner::getInstance($mockQueueProvider);
        $instance2 = JobRunner::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test getInstance accepts PKPQueueProvider
     */
    public function testGetInstanceAcceptsPKPQueueProvider(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();

        $instance = JobRunner::getInstance($mockQueueProvider);

        $this->assertInstanceOf(JobRunner::class, $instance);
    }

    /**
     * Test getInstance falls back to the container's `pkpJobQueue` binding when
     * no provider is supplied.
     */
    public function testGetInstanceFallsBackToContainer(): void
    {
        JobRunner::resetInstance();

        // No provider passed → should resolve app('pkpJobQueue')
        $runner = JobRunner::getInstance();

        $this->assertInstanceOf(JobRunner::class, $runner);

        $reflection = new ReflectionClass($runner);
        $property = $reflection->getProperty('jobQueue');
        $property->setAccessible(true);

        $this->assertSame(app('pkpJobQueue'), $property->getValue($runner));
    }

    /**
     * Test constructor is private
     */
    public function testConstructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(JobRunner::class);
        $constructor = $reflection->getConstructor();

        $this->assertTrue(
            $constructor->isPrivate(),
            'JobRunner constructor should be private'
        );
    }

    /**
     * Test resetInstance clears singleton
     */
    public function testResetInstanceClearsSingleton(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();

        $instance1 = JobRunner::getInstance($mockQueueProvider);

        JobRunner::resetInstance();

        $instance2 = JobRunner::getInstance($mockQueueProvider);

        $this->assertNotSame($instance1, $instance2);
    }

    // ========================================
    // Constraint Tests - Job Limit
    // ========================================

    /**
     * Test exceededJobLimit returns true when limit reached
     */
    public function testExceededJobLimitReturnsTrueWhenLimitReached(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner
            ->setMaxJobsToProcess(5)
            ->withMaxJobsConstrain();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededJobLimit');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($runner, 5));
        $this->assertTrue($method->invoke($runner, 10));
    }

    /**
     * Test exceededJobLimit returns false when under limit
     */
    public function testExceededJobLimitReturnsFalseWhenUnderLimit(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner->setMaxJobsToProcess(5);
        $runner->withMaxJobsConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededJobLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, 0));
        $this->assertFalse($method->invoke($runner, 4));
    }

    // ========================================
    // Constraint Tests - Time Limit
    // ========================================

    /**
     * Test exceededTimeLimit returns true when time exceeded
     */
    public function testExceededTimeLimitReturnsTrueWhenTimeExceeded(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner->setMaxTimeToProcessJobs(10);
        $runner->withMaxExecutionTimeConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededTimeLimit');
        $method->setAccessible(true);

        // Simulate start time 15 seconds ago
        $startTime = time() - 15;

        $this->assertTrue($method->invoke($runner, $startTime));
    }

    /**
     * Test exceededTimeLimit returns false when under limit
     */
    public function testExceededTimeLimitReturnsFalseWhenUnderLimit(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner->setMaxTimeToProcessJobs(30);
        $runner->withMaxExecutionTimeConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededTimeLimit');
        $method->setAccessible(true);

        // Simulate start time 5 seconds ago
        $startTime = time() - 5;

        $this->assertFalse($method->invoke($runner, $startTime));
    }

    // ========================================
    // Constraint Tests - Memory Limit
    // ========================================

    /**
     * Test exceededMemoryLimit returns true when memory exceeded
     */
    public function testExceededMemoryLimitReturnsTrueWhenMemoryExceeded(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set a very low memory limit (current usage + 1 byte)
        $runner->setMaxMemoryToConsumed(1);
        $runner->withMaxMemoryConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededMemoryLimit');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($runner));
    }

    /**
     * Test exceededMemoryLimit returns false when under limit
     */
    public function testExceededMemoryLimitReturnsFalseWhenUnderLimit(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        // Set a very high memory limit
        $runner->setMaxMemoryToConsumed(PHP_INT_MAX);
        $runner->withMaxMemoryConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededMemoryLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner));
    }

    // ========================================
    // Constraint Tests - Estimated Time
    // ========================================

    /**
     * Test mayExceedMemoryLimitAtNextJob (despite its name, this estimates the
     * next job's processing TIME, not memory) returns true when the projected
     * time to finish the next job would exceed the max execution time.
     */
    public function testEstimatedNextJobTimeExceedsLimitReturnsTrue(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $runner->setMaxTimeToProcessJobs(10);
        $runner->withMaxExecutionTimeConstrain();
        $runner->withEstimatedTimeToProcessNextJobConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('mayExceedMemoryLimitAtNextJob');
        $method->setAccessible(true);

        // Test with 5 jobs processed, start time 8 seconds ago
        // Average per job = 8/5 = 1.6 seconds
        // Estimated next job with 3x multiplier = 8 + (1.6 * 3) = 12.8 seconds
        // This exceeds 10 second max
        $startTime = time() - 8;
        $this->assertTrue($method->invoke($runner, 5, $startTime));
    }

    // ========================================
    // Constraint Tests - Config Methods
    // ========================================

    /**
     * Test deduceSafeMaxExecutionTime uses config value
     */
    public function testDeduceSafeMaxExecutionTimeUsesConfigValue(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('deduceSafeMaxExecutionTime');
        $method->setAccessible(true);

        $result = $method->invoke($runner);

        // Should return a positive integer
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * Test deduceSafeMaxAllowedMemory parses percentage
     */
    public function testDeduceSafeMaxAllowedMemoryParsesPercentage(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('deduceSafeMaxAllowedMemory');
        $method->setAccessible(true);

        $result = $method->invoke($runner);

        // Should return a positive integer (bytes)
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ========================================
    // Constraint Tests - Setter Methods
    // ========================================

    /**
     * Test withMaxJobsConstrain enables constraint
     */
    public function testWithMaxJobsConstrainEnablesConstraint(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $result = $runner->withMaxJobsConstrain();

        $this->assertSame($runner, $result);
        $this->assertNotNull($runner->getMaxJobsToProcess());
    }

    /**
     * Test withMaxExecutionTimeConstrain enables constraint
     */
    public function testWithMaxExecutionTimeConstrainEnablesConstraint(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $result = $runner->withMaxExecutionTimeConstrain();

        $this->assertSame($runner, $result);
        $this->assertNotNull($runner->getMaxTimeToProcessJobs());
    }

    /**
     * Test withMaxMemoryConstrain enables constraint
     */
    public function testWithMaxMemoryConstrainEnablesConstraint(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $result = $runner->withMaxMemoryConstrain();

        $this->assertSame($runner, $result);
        $this->assertNotNull($runner->getMaxMemoryToConsumed());
    }

    /**
     * Test withEstimatedTimeConstrain enables constraint
     */
    public function testWithEstimatedTimeConstrainEnablesConstraint(): void
    {
        $mockQueueProvider = $this->createMockQueueProvider();
        $runner = JobRunner::getInstance($mockQueueProvider);

        $result = $runner->withEstimatedTimeToProcessNextJobConstrain();

        $this->assertSame($runner, $result);
    }

    // ========================================
    // Constraint Tests - Disabled by default (no with*Constrain() call)
    // ========================================

    /**
     * exceededJobLimit must short-circuit to false when the job-count
     * constraint is not enabled, regardless of how many jobs were processed.
     */
    public function testExceededJobLimitReturnsFalseWhenConstraintDisabled(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        // Configure a value but DO NOT enable the constraint.
        $runner->setMaxJobsToProcess(1);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededJobLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, PHP_INT_MAX));
    }

    /**
     * exceededTimeLimit must short-circuit to false when the execution-time
     * constraint is not enabled, even for a long-elapsed start time.
     */
    public function testExceededTimeLimitReturnsFalseWhenConstraintDisabled(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        $runner->setMaxTimeToProcessJobs(1);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededTimeLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, time() - 100000));
    }

    /**
     * exceededMemoryLimit must short-circuit to false when the memory
     * constraint is not enabled, even with a tiny configured limit.
     */
    public function testExceededMemoryLimitReturnsFalseWhenConstraintDisabled(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        $runner->setMaxMemoryToConsumed(1);

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededMemoryLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner));
    }

    /**
     * exceededTimeLimit treats the threshold as inclusive (>=): elapsed time
     * exactly equal to the max should be considered exceeded.
     */
    public function testExceededTimeLimitReturnsTrueAtExactThreshold(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        $runner->setMaxTimeToProcessJobs(10)->withMaxExecutionTimeConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('exceededTimeLimit');
        $method->setAccessible(true);

        // Exactly 10 seconds elapsed (>= 10 → exceeded).
        $this->assertTrue($method->invoke($runner, time() - 10));
    }

    // ========================================
    // Estimated-next-job-time false paths
    // ========================================

    /**
     * No estimate when the estimate constraint itself is not enabled.
     */
    public function testEstimatedNextJobTimeReturnsFalseWhenEstimateConstraintDisabled(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        // Time constraint on, but estimate constraint off.
        $runner->setMaxTimeToProcessJobs(10)->withMaxExecutionTimeConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('mayExceedMemoryLimitAtNextJob');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, 5, time() - 8));
    }

    /**
     * No estimate when there is no execution-time constraint to compare against.
     */
    public function testEstimatedNextJobTimeReturnsFalseWhenTimeConstraintDisabled(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        // Estimate constraint on, but no execution-time constraint.
        $runner->withEstimatedTimeToProcessNextJobConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('mayExceedMemoryLimitAtNextJob');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, 5, time() - 8));
    }

    /**
     * No estimate (and no division by zero) when no job has been processed yet.
     */
    public function testEstimatedNextJobTimeReturnsFalseWhenNoJobsProcessed(): void
    {
        $runner = JobRunner::getInstance($this->createMockQueueProvider());

        $runner
            ->setMaxTimeToProcessJobs(10)
            ->withMaxExecutionTimeConstrain()
            ->withEstimatedTimeToProcessNextJobConstrain();

        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('mayExceedMemoryLimitAtNextJob');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($runner, 0, time() - 8));
    }

    // ========================================
    // Availability scopes — stale reserved-job recovery
    //
    // These exercise the Job query scopes that decide which jobs the runner
    // treats as "available" (via PKPQueueProvider::getJobModelBuilder()). They
    // test query-building only (mocked Builder + compiled SQL); no table rows
    // are read or written.
    // ========================================

    /**
     * scopeIsReservedButExpired constrains the query to reservations older than
     * `now - retry_after`.
     */
    public function testScopeIsReservedButExpiredAddsExpiryConstraint(): void
    {
        $job = new Job();
        $expectedExpiry = time() - $this->retryAfter();

        $mockQuery = Mockery::mock(EloquentBuilder::class);
        $mockQuery->shouldReceive('where')
            ->once()
            ->with('reserved_at', '<=', Mockery::on(
                // Allow a small delta to absorb the second tick during the call.
                fn ($value) => is_int($value) && abs($value - $expectedExpiry) <= 2
            ))
            ->andReturnSelf();

        $result = $job->scopeIsReservedButExpired($mockQuery);

        $this->assertSame($mockQuery, $result);
    }

    /**
     * When retry_after is not configured, the scope is a no-op: it adds no
     * constraint and returns the query untouched (so scopeIsAvailable safely
     * degrades to the unreserved-only behaviour).
     */
    public function testScopeIsReservedButExpiredIsNoOpWhenRetryAfterMissing(): void
    {
        config(['queue.connections.database.retry_after' => null]);

        $job = new Job();

        $mockQuery = Mockery::mock(EloquentBuilder::class);
        $mockQuery->shouldNotReceive('where');

        $result = $job->scopeIsReservedButExpired($mockQuery);

        $this->assertSame($mockQuery, $result);
    }

    /**
     * scopeIsAvailable() must OR together two branches:
     *   (a) unreserved & available now: reserved_at IS NULL AND available_at <= now
     *   (b) stale reservation:          reserved_at <= now - retry_after
     *
     * This is the exact behaviour #11999 introduced. We assert it against the
     * compiled SQL + bindings (no rows are touched). A regression that drops
     * branch (b) — e.g. calling the scope through Eloquent's magic dispatch so
     * the constraint lands on a throwaway builder — would fail here.
     */
    public function testScopeIsAvailableIncludesStaleReservedBranch(): void
    {
        $query = Job::query()->isAvailable();

        $sql = strtolower($query->toSql());
        $bindings = $query->getBindings();

        // Branch (a): the unreserved-and-available condition is present.
        $this->assertStringContainsString('reserved_at', $sql);
        $this->assertStringContainsString('available_at', $sql);
        $this->assertStringContainsString('is null', $sql);

        // Branch (b) is OR'd in — `reserved_at` is referenced for both the
        // "is null" check and the stale "<=" check, and the two branches are
        // combined with OR.
        $this->assertStringContainsString(' or ', $sql);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($sql, 'reserved_at'),
            'scopeIsAvailable() should reference reserved_at in both branches'
        );

        // Two bound values: available_at <= now, and reserved_at <= now - retry_after.
        $this->assertCount(2, $bindings);
        $this->assertEqualsWithDelta(time(), (int) $bindings[0], 5);
        $this->assertEqualsWithDelta(time() - $this->retryAfter(), (int) $bindings[1], 5);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create a mock PKPQueueProvider
     */
    protected function createMockQueueProvider(): PKPQueueProvider
    {
        $mockBuilder = Mockery::mock(EloquentBuilder::class);
        $mockBuilder->shouldReceive('count')->andReturn(0);

        $mockQueueProvider = Mockery::mock(PKPQueueProvider::class);
        $mockQueueProvider->shouldReceive('getJobModelBuilder')->andReturn($mockBuilder);

        return $mockQueueProvider;
    }

    /**
     * The configured retry_after threshold (seconds) used to decide staleness.
     * PKPContainer sets queue.connections.database.retry_after to 610.
     */
    protected function retryAfter(): int
    {
        return (int) config('queue.connections.database.retry_after');
    }
}
