<?php

/**
 * @file tests/jobs/submissions/UpdateSubmissionSearchJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the submission search reindexing job.
 */

namespace PKP\tests\jobs\submissions;

use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\submissions\UpdateSubmissionSearchJob;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(UpdateSubmissionSearchJob::class)]
class UpdateSubmissionSearchJobTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:46:"PKP\jobs\submissions\UpdateSubmissionSearchJob":3:{s:15:"\0*\0submissionId";i:1;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            UpdateSubmissionSearchJob::class,
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
        /** @var UpdateSubmissionSearchJob $updateSubmissionSearchJob */
        $updateSubmissionSearchJob = unserialize($this->serializedJobData);

        $fakeSubmission = new \APP\submission\Submission();
        $fakeSubmission->setData('publications', collect([new \APP\publication\Publication()]));

        // Mock the Submission facade to return a fake submission when Repo::submission()->get($id) is called
        $mock = Mockery::mock(app(\APP\submission\Repository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($fakeSubmission)
            ->getMock();

        app()->instance(\APP\submission\Repository::class, $mock);

        $updateSubmissionSearchJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
