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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\submissions\RemoveSubmissionFileFromSearchIndexJob;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(RemoveSubmissionFileFromSearchIndexJob::class)]
class RemoveSubmissionFileFromSearchIndexJobTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:59:"PKP\\jobs\\submissions\\RemoveSubmissionFileFromSearchIndexJob":4:{s:15:"\0*\0submissionId";i:25;s:19:"\0*\0submissionFileId";i:55;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

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
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var RemoveSubmissionFileFromSearchIndexJob $removeSubmissionFileFromSearchIndexJob */
        $removeSubmissionFileFromSearchIndexJob = unserialize($this->serializedJobData);

        $removeSubmissionFileFromSearchIndexJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
