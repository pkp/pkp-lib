<?php

/**
 * @file tests/jobs/submissions/RemoveExpiredInvitationsJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the submission search reindexing job.
 */

namespace PKP\tests\jobs\invitations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\invitations\RemoveExpiredInvitationsJob;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(RemoveExpiredInvitationsJob::class)]
class RemoveExpiredInvitationsJobTest extends PKPTestCase
{
    /**
     * serialization from OJS 3.4.0
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
