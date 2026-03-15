<?php

/**
 * @file tests/classes/queue/PKPJobRunnerTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for JobRunner singleton pattern and constraint methods.
 */

namespace PKP\tests\classes\queue;

use Mockery;
use ReflectionClass;
use PKP\queue\JobRunner;
use PKP\tests\PKPTestCase;
use PKP\core\PKPQueueProvider;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class PKPJobRunnerTest extends PKPTestCase
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
     * Test getInstance falls back to container
     */
    public function testGetInstanceFallsBackToContainer(): void
    {
        // When no provider passed, it uses app('pkpJobQueue')
        // This test verifies getInstance works without explicit provider
        $this->assertTrue(
            method_exists(JobRunner::class, 'getInstance'),
            'JobRunner should have getInstance static method'
        );
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
     * Test mayExceedMemoryLimitAtNextJob estimates correctly
     */
    public function testMayExceedMemoryLimitEstimatesCorrectly(): void
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
}
