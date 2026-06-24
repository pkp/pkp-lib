<?php

/**
 * @file tests/jobs/submissions/RemoveSubmissionFileFromSearchIndexJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for removal of submission file from search index job.
 */

namespace PKP\tests\jobs\submissions;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use PKP\jobs\submissions\RemoveSubmissionFileFromSearchIndexJob;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class RemoveSubmissionFileFromSearchIndexJobTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:59:"PKP\\jobs\\submissions\\RemoveSubmissionFileFromSearchIndexJob":4:{s:15:"\0*\0submissionId";i:25;s:19:"\0*\0submissionFileId";i:55;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [
            ...parent::getMockedDAOs(),
            $this->getAppSearchDaoKey(),
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            RemoveSubmissionFileFromSearchIndexJob::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Test job is a proper context aware job instance and getContextId returns expected value
     */
    public function testUnserializationGetProperContextId(): void
    {
        $job = unserialize($this->serializedJobData);
        $this->assertInstanceOf(\PKP\queue\ContextAwareJob::class, $job);

        $submissionMock = Mockery::mock(\APP\submission\Submission::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('contextId')
            ->andReturn(99)
            ->getMock();

        $submissionRepoMock = Mockery::mock(app(\APP\submission\Repository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($submissionMock)
            ->getMock();

        app()->instance(\APP\submission\Repository::class, $submissionRepoMock);
        
        $this->assertIsInt($job->getContextId());
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var RemoveSubmissionFileFromSearchIndexJob $removeSubmissionFileFromSearchIndexJob */
        $removeSubmissionFileFromSearchIndexJob = unserialize($this->serializedJobData);

        $submissionSearchDAOMock = Mockery::mock(\PKP\search\SubmissionSearchDAO::class)
            ->makePartial()
            ->shouldReceive(['deleteSubmissionKeywords' => null])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO($this->getAppSearchDaoKey(), $submissionSearchDAOMock);

        // Test that the job can be handled without causing an exception.
        $this->assertNull($removeSubmissionFileFromSearchIndexJob->handle());
    }
}

