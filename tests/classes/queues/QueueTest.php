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
import('lib.pkp.tests.PKPTestCase');

use Illuminate\Support\Facades\Queue;

use PKP\config\Config;

class QueueTest extends PKPTestCase
{
    protected $configData;

    protected $tmpErrorLog;
    protected $originalErrorLog;

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        $this->configData = Config::getData();

        if (!$this->configData['queues']['run_jobs_at_shutdown']) {
            $this->markTestSkipped('Config [\'queues\'][\'run_jobs_at_shutdown\'] isnt enabled.');
        }

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();
        ini_set(
            'error_log',
            stream_get_meta_data($this->tmpErrorLog)['uri']
        );

        parent::setUp();
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
