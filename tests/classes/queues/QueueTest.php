<?php
/**
 * @file tests/classes/queues/QueueTest.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueueTest
 * @ingroup tests_classes_queues
 *
 * @see Queue
 *
 * @brief Test class for the Queues process
 */

namespace PKP\tests\classes\queues;

use Illuminate\Support\Facades\Queue;
use PKP\config\Config;
use PKP\tests\PKPTestCase;

class QueueTest extends PKPTestCase
{
    protected array $configData;

    protected $tmpErrorLog;
    protected string $originalErrorLog;

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configData = Config::getData();

        if ($this->configData['queues']['disable_jobs_run_at_shutdown']) {
            $this->markTestSkipped("Cannot test queues with the config [queues].disable_jobs_run_at_shutdown enabled.");
        }

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();
        ini_set(
            'error_log',
            stream_get_meta_data($this->tmpErrorLog)['uri']
        );
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        ini_set(
            'error_log',
            $this->originalErrorLog
        );
        parent::tearDown();
    }

    /**
     * @covers Queue Worker
     */
    public function testPuttingJobsAtQueue()
    {
        Queue::fake();

        $queue = $this->configData['queues']['default_queue'] ?? 'php-unit';

        $jobContent = 'exampleContent';

        Queue::push($jobContent, [], $queue);

        Queue::assertPushedOn($queue, $jobContent);
    }
}
