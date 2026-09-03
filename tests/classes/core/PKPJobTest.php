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
use Illuminate\Database\Connection;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PKP\job\models\Job as PKPJobModel;
use PKP\config\Config;
use PKP\jobs\BaseJob;
use PKP\jobs\statistics\CompileContextMetrics;
use PKP\jobs\statistics\RemoveDoubleClicks;
use PKP\jobs\testJobs\TestJobFailure;
use PKP\jobs\testJobs\TestJobSuccess;
use PKP\tests\PKPTestCase;
use ReflectionClass;

class PKPJobTest extends PKPTestCase
{
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    protected $busInstance, $queueInstance;

    protected ?int $originalRetryAfter = null;

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();

        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);

        // Store originals before any test modifies them
        $this->busInstance = Bus::getFacadeRoot();
        $this->queueInstance = Queue::getFacadeRoot();
        $this->originalRetryAfter = config('queue.connections.database.retry_after');
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        // Restore originals after each test
        Bus::swap($this->busInstance);
        Queue::swap($this->queueInstance);
        config(['queue.connections.database.retry_after' => $this->originalRetryAfter]);

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

        $queue = Config::getVar('queues', 'default_queue', 'php-unit');

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
        $runner = app('jobRunner'); /** @var \PKP\queue\JobRunner $runner */
        $runner
            ->setJobQueue($jobQueue->forQueue(PKPJobModel::TESTING_QUEUE))
            ->setMaxJobsToProcess(2)
            ->withMaxJobsConstrain()
            ->setMaxTimeToProcessJobs(10)
            ->withMaxExecutionTimeConstrain();

        $result = $runner->processJobs();
        $this->assertTrue($result);
        $this->assertEquals(1, PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->count());
        $this->assertEquals(2, $runner->getJobProcessedCount());

        $result = $runner->processJobs();
        $this->assertTrue($result);
        $this->assertEquals(0, PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->count());
        $this->assertEquals(1, $runner->getJobProcessedCount());
    }

    /**
     * Covers that the configured `[queues] retry_after` reaches the queue connection,
     * which is where Laravel reads it from to decide that a reserved job is abandoned,
     * and that it is never resolved below the 610 second default
     */
    public function testConfiguredRetryAfterReachesTheQueueConnection()
    {
        $this->assertSame(
            max(610, (int) Config::getVar('queues', 'retry_after', 610)),
            (int) config('queue.connections.database.retry_after')
        );
    }

    /**
     * Covers the timeout a long running job derives from the connection's `retry_after`
     */
    public function testLongRunningJobDerivesTimeoutFromRetryAfter()
    {
        config(['queue.connections.database.retry_after' => 3600]);

        $this->assertSame(3600 - $this->timeoutReduction(), (new LongRunningTestJob())->timeout);
    }

    /**
     * Covers that a job which does not opt in keeps its declared timeout, however
     * large `retry_after` is
     */
    public function testOrdinaryJobKeepsItsDeclaredTimeout()
    {
        config(['queue.connections.database.retry_after' => 3600]);

        $this->assertSame(60, (new OrdinaryTestJob())->timeout);
    }

    /**
     * Covers that the derived timeout takes precedence over a timeout the job declares
     * itself, which is what keeps an opted in job inside the window its reservation is
     * held for, whatever `retry_after` is configured to
     */
    public function testDerivedTimeoutOverridesADeclaredTimeout()
    {
        config(['queue.connections.database.retry_after' => 300]);

        $this->assertSame(300 - $this->timeoutReduction(), (new DeclaredTimeoutTestJob())->timeout);
    }

    /**
     * Covers that connections defining no `retry_after`, such as `sync`, leave the
     * timeout alone rather than derive a negative value from null, which would cancel
     * the worker's alarm instead of shortening it
     */
    public function testMissingRetryAfterLeavesTimeoutUntouched()
    {
        config(['queue.connections.database.retry_after' => null]);

        $this->assertSame(60, (new LongRunningTestJob())->timeout);
        $this->assertSame(600, (new DeclaredTimeoutTestJob())->timeout);
    }

    /**
     * Covers that the derived timeout reaches the queued payload, which is the value
     * Laravel actually enforces: Worker::timeoutForJob() reads $job->timeout(), the
     * payload key stamped by Queue::createObjectPayload() at dispatch time, not the
     * property on the unserialized object
     */
    public function testDerivedTimeoutReachesTheDispatchedPayload()
    {
        config(['queue.connections.database.retry_after' => 3600]);

        $this->assertSame(
            3600 - $this->timeoutReduction(),
            $this->buildPayloadFor(new LongRunningTestJob())['timeout']
        );

        $this->assertSame(60, $this->buildPayloadFor(new OrdinaryTestJob())['timeout']);
    }

    /**
     * Covers the usage statistics jobs, the reason this mechanism exists. They are
     * dispatched as a single chain, so every member has to opt in or the chain would
     * run under mixed timeout regimes
     */
    public function testUsageStatisticsJobsOptInToTheDerivedTimeout()
    {
        config(['queue.connections.database.retry_after' => 3600]);

        $expected = 3600 - $this->timeoutReduction();

        // Opted in: a self joining DELETE over the day's temporary records
        $this->assertSame($expected, (new RemoveDoubleClicks('20260824.log'))->timeout);

        // Not opted in: a single pass aggregation in the same chain keeps the default,
        // so the opt in stays a deliberate per job decision rather than a chain wide one
        $this->assertSame(60, (new CompileContextMetrics('20260824.log'))->timeout);
    }

    /**
     * Build the queue payload for a job exactly as Laravel does on dispatch, without
     * touching the database: createObjectPayload() only reads properties off the job
     * and serializes it
     */
    protected function buildPayloadFor(BaseJob $job): array
    {
        $queue = new DatabaseQueue(Mockery::mock(Connection::class), 'jobs', 'queue');

        $method = (new ReflectionClass(DatabaseQueue::class))->getMethod('createObjectPayload');
        $method->setAccessible(true);

        return $method->invoke($queue, $job, 'queue');
    }

    /**
     * BaseJob::TIMEOUT_REDUCTION, the gap kept between a derived timeout and `retry_after`
     */
    protected function timeoutReduction(): int
    {
        return (new ReflectionClass(BaseJob::class))->getConstant('TIMEOUT_REDUCTION');
    }
}

/**
 * The fixtures below are named rather than anonymous classes because
 * Queue::createObjectPayload() serializes the job, and PHP cannot serialize an
 * instance of an anonymous class.
 */

/** A job that opts in to the long running timeout */
class LongRunningTestJob extends BaseJob
{
    protected bool $isLongRunning = true;

    public function handle(): void
    {
    }
}

/** A job that keeps the BaseJob defaults */
class OrdinaryTestJob extends BaseJob
{
    public function handle(): void
    {
    }
}

/** A long running job that also declares a timeout of its own, as the statistics jobs do */
class DeclaredTimeoutTestJob extends BaseJob
{
    public int $timeout = 600;

    protected bool $isLongRunning = true;

    public function handle(): void
    {
    }
}
