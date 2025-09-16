<?php

/**
 * @file tests/classes/core/PKPJobTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPJobTest
 *
 * @ingroup tests_classes_core
 *
 * @brief Tests for the Job dispatching.
 */

namespace PKP\tests\classes\core;

use Exception;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PKP\queue\JobRunner;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\TestJobFailure;
use PKP\jobs\testJobs\TestJobSuccess;
use PKP\tests\PKPTestCase;

class PKPJobTest extends PKPTestCase
{
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    protected $busInstance, $queueInstance;

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();

        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);

        $this->busInstance = Bus::getFacadeRoot();
        $this->queueInstance = Queue::getFacadeRoot();
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        Bus::swap($this->busInstance);
        Queue::swap($this->queueInstance);

        // Delete any job on test queue on test teardown
        PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->delete();

        parent::tearDown();
    }

    /**
     * Covers Job exception handling
     */
    public function testJobExceptionOnSync()
    {
        $this->expectException(Exception::class);

        TestJobFailure::dispatchSync();
    }

    /**
     * Covers Job dispatching
     */
    public function testJobDispatch()
    {
        Bus::fake();

        TestJobFailure::dispatch();
        TestJobSuccess::dispatch();

        Bus::assertDispatched(TestJobFailure::class);
        Bus::assertDispatched(TestJobSuccess::class);
    }

    /**
     * Covers Job dispatching in chain
     */
    public function testJobDispatchInChain()
    {
        Bus::fake();

        Bus::chain([
            new TestJobFailure(),
            new TestJobSuccess(),
        ])->dispatch();

        Bus::assertChained([
            TestJobFailure::class,
            TestJobSuccess::class,
        ]);
    }

    /**
     * Covers Job dispatching in batch
     */
    public function testJobDispatchInBatch()
    {
        Bus::fake();

        Bus::batch([
            new TestJobSuccess(),
            new TestJobSuccess(),
            new TestJobFailure(),
            new TestJobFailure(),
        ])->name('test-jobs')->dispatch();

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->name === 'test-jobs' && $batch->jobs->count() === 4;
        });
    }

    /**
     * Covers Queue Worker
     */
    public function testPuttingJobsAtQueue()
    {
        Queue::fake();

        $jobContent = 'exampleContent';

        Queue::push($jobContent, [], PKPJobModel::TESTING_QUEUE);

        Queue::assertPushedOn(PKPJobModel::TESTING_QUEUE, $jobContent);
    }

    /**
     * Covers Job Runner with basic constraints
     * 
     * We had to dispatch the jobs in the test queue with real connection
     * as faking queue will not work with the Job Runner
     */
    public function testJobRunnerProcessesJobsWithConstraints()
    {
        // Dispatch multiple test jobs in test queue
        $jobCount = 3;
        for ($i = 0; $i < $jobCount; $i++) {
            dispatch(new TestJobSuccess());
        }

        // Configure JobRunner with constraints and `pkpJobQueue` with test queue
        $jobQueue = app('pkpJobQueue'); /** @var \PKP\core\PKPQueueProvider $jobQueue */
        $runner = new JobRunner($jobQueue->forQueue(PKPJobModel::TESTING_QUEUE));
        $runner
            ->setMaxJobsToProcess(2)
            ->withMaxJobsConstrain()
            ->setMaxTimeToProcessJobs(10)
            ->withMaxExecutionTimeConstrain()
            ->setMaxMemoryToConsumed(1024 * 1024 * 30) // 30MB
            ->withMaxMemoryConstrain();

        // Process the jobs
        $result = $runner->processJobs();
        $this->assertTrue($result);
        $this->assertEquals(1, PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->count());

        $result = $runner->processJobs();
        $this->assertTrue($result);
        $this->assertEquals(0, PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->count());
    }
}
