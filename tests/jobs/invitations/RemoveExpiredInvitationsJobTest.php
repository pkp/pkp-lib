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

namespace PKP\tests\jobs\invitations;

use PKP\tests\PKPTestCase;
use PKP\jobs\invitations\RemoveExpiredInvitationsJob;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(RemoveExpiredInvitationsJob::class)]
class RemoveExpiredInvitationsJobTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:48:"PKP\jobs\invitations\RemoveExpiredInvitationsJob":2:{s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            RemoveExpiredInvitationsJob::class,
            unserialize($this->serializedJobData)
        );
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var RemoveExpiredInvitationsJob $removeExpiredInvitationsJob */
        $removeExpiredInvitationsJob = unserialize($this->serializedJobData);

        $removeExpiredInvitationsJob->handle();

        $this->expectNotToPerformAssertions();
    }
}

