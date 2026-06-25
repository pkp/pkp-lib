<?php

/**
 * @file tests/classes/queue/PKPQueueEventTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for Queue event lifecycle handlers (before, after, failing).
 */

namespace PKP\tests\classes\queue;

use APP\core\Application;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobProcessing;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPQueueProvider;
use PKP\db\DAORegistry;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\TestJobFailure;
use PKP\jobs\testJobs\TestJobSuccess;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPQueueProvider::class)]
class PKPQueueEventTest extends PKPTestCase
{
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();

        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        Application::get()->clearCliContext();
        Mockery::close();

        // Delete any job on test queue on test teardown
        PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->delete();

        parent::tearDown();
    }

    /**
     * Setup mock context DAO that returns mock contexts for given IDs
     */
    protected function setupMockContextForId(int $contextId): \Mockery\MockInterface|\PKP\context\Context
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn($contextId);

        $application = Application::get();
        $contextDao = $application->getContextDAO();

        $mockDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getById'])
            ->getMock();

        $mockDao->expects($this->any())
            ->method('getById')
            ->with($contextId)
            ->willReturn($mockContext);

        DAORegistry::registerDAO(
            match (Application::get()->getName()) {
                'ojs2' => 'JournalDAO',
                'omp' => 'PressDAO',
                'ops' => 'ServerDAO',
            },
            $mockDao
        );

        return $mockContext;
    }

    /**
     * Setup mock context DAO whose getById() returns null for the given id (context does not exist)
     */
    protected function setupMissingContextForId(int $contextId): void
    {
        $contextDao = Application::get()->getContextDAO();

        $mockDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getById'])
            ->getMock();

        $mockDao->expects($this->any())
            ->method('getById')
            ->with($contextId)
            ->willReturn(null);

        DAORegistry::registerDAO(
            match (Application::get()->getName()) {
                'ojs2' => 'JournalDAO',
                'omp' => 'PressDAO',
                'ops' => 'ServerDAO',
            },
            $mockDao
        );
    }

    /**
     * Process the next job in test queue via queue worker
     */
    protected function processNextTestJob(): void
    {
        $worker = app('queue.worker');
        $jobQueue = app('pkpJobQueue');

        $worker->runNextJob(
            Config::getVar('queues', 'default_connection', 'database'),
            PKPJobModel::TESTING_QUEUE,
            $jobQueue->getWorkerOptions()
        );
    }

    /**
     * Test CLI context cleared even on exception
     */
    public function testCliContextClearedEvenOnException(): void
    {
        $mockContext = $this->setupMockContextForId(42);
        Application::get()->setCliContext(42);
        $this->assertEquals($mockContext->getId(), Application::get()->getCliContext()->getId());

        dispatch(new TestJobFailure());

        $this->processNextTestJob();

        $this->assertNull(
            Application::get()->getCliContext(),
            'CLI context should be cleared after exception'
        );
    }

    /**
     * Test CLI context cleared successful completion
     */
    public function testCliContextClearedOnSuccessfulJobProcessing(): void
    {
        $mockContext = $this->setupMockContextForId(41);
        Application::get()->setCliContext(41);
        $this->assertEquals($mockContext->getId(), Application::get()->getCliContext()->getId());

        dispatch(new TestJobSuccess());

        $this->processNextTestJob();

        $this->assertNull(
            Application::get()->getCliContext(),
            'CLI context should be cleared after successful job processing completion'
        );
    }

    /**
     * Test that JobProcessing event contains expected payload structure
     */
    public function testJobProcessingEventPayloadStructure(): void
    {
        // Create a mock job with payload
        $mockJob = Mockery::mock(JobContract::class);
        $mockJob->shouldReceive('payload')->andReturn([
            'displayName' => 'TestJob',
            'context_id' => 1,
            'job' => 'serialized_job_data'
        ]);
        $mockJob->shouldReceive('getConnectionName')->andReturn('database');

        $event = new JobProcessing('database', $mockJob);

        $payload = $event->job->payload();
        $this->assertArrayHasKey('context_id', $payload);
        $this->assertEquals(1, $payload['context_id']);
    }

    /**
     * Test that the Queue::before listener fails the job IMMEDIATELY (no retries) when its context_id no
     * longer resolves to a context (e.g. the journal was deleted after the job was enqueued). The
     * listener calls $event->job->fail() rather than throwing, so the job goes straight to failed_jobs.
     */
    public function testInvalidContextIdFailsJobImmediately(): void
    {
        $this->setupMissingContextForId(999999);

        $failedWith = null;
        $mockJob = Mockery::mock(JobContract::class);
        $mockJob->shouldReceive('payload')->andReturn([
            'displayName' => 'TestJob',
            'context_id' => 999999,
        ]);
        $mockJob->shouldReceive('fail')->once()->with(Mockery::on(function ($e) use (&$failedWith) {
            $failedWith = $e;
            return $e instanceof \RuntimeException;
        }));

        // Dispatching the event directly invokes the registered Queue::before listener, which fails the
        // job in place (no throw to surface).
        app('events')->dispatch(new JobProcessing('database', $mockJob));

        $this->assertInstanceOf(\RuntimeException::class, $failedWith);
        $this->assertStringContainsString('Invalid context_id 999999', $failedWith->getMessage());
    }
}
