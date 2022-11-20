<?php

/**
 * @file tests/classes/core/PKPJobTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPJobTest
 * @ingroup tests_classes_core
 *
 * @brief Tests for the Job dispatching.
 */

namespace PKP\tests\classes\core;

use Exception;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\tests\PKPTestCase;
use PKP\jobs\testJobs\TestJobFailure;
use PKP\jobs\testJobs\TestJobSuccess;

class PKPJobTest extends PKPTestCase
{
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();

        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        parent::tearDown();
    }
    
    /**
     * @covers Job exception handling
     */
    public function testJobExceptionOnSync()
    {   
        $this->expectException(Exception::class);

        TestJobFailure::dispatchSync();
    }

    /**
     * @covers Job dispatching
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
     * @covers Job dispatching in chain
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
     * @covers Job dispatching in batch
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

        Bus::assertBatched(function(PendingBatch $batch) {
            return $batch->name === 'test-jobs' && $batch->jobs->count() === 4;
        });
    }

    /**
     * @covers Queue Worker
     */
    public function testPuttingJobsAtQueue()
    {
        Queue::fake();

        $queue = Config::getVar('queues', 'default_queue', 'php-unit');

        $jobContent = 'exampleContent';

        Queue::push($jobContent, [], $queue);

        Queue::assertPushedOn($queue, $jobContent);
    }
}