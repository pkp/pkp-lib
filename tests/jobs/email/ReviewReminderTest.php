<?php

/**
 * @file tests/jobs/email/ReviewReminderTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for review response and submit due reminder job.
 */

namespace PKP\tests\classes\core;

use Mockery;
use APP\core\Services;
use PKP\tests\PKPTestCase;
use PKP\jobs\email\ReviewReminder;
use Illuminate\Support\Facades\Mail;
use PKP\submission\reviewAssignment\ReviewAssignment;
use APP\submission\Repository as SubmissionRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\invitation\repositories\Invitation as InvitationRepository;
use PKP\submission\reviewAssignment\Repository as ReviewAssignmentRepository;
use PKP\log\event\Repository as EventRepository;

/**
 * @runTestsInSeparateProcesses
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class ReviewReminderTest extends PKPTestCase
{
    /**
     * Serializion from OJS 3.5.0
     */
    protected string $serializedJobData = 'O:29:"PKP\jobs\email\ReviewReminder":5:{s:9:"contextId";i:1;s:18:"reviewAssignmentId";i:57;s:13:"mailableClass";s:43:"PKP\mail\mailables\ReviewResponseRemindAuto";s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperReviewReminderJobInstance(): void
    {
        $this->assertInstanceOf(ReviewReminder::class, unserialize($this->serializedJobData));
    }

    /**
     * Test job will not fail when no reviewer associated with review assignment
     */
    public function testJobWillRunWithIfNoReviewerExists(): void
    {
        $reviewReminderJob = unserialize($this->serializedJobData);

        $reviewAssignmentMock = Mockery::mock(ReviewAssignment::class)
            ->shouldReceive([
                'getReviewerId' => 0,
                'getData' => 0,
                'getSubmissionId' => 0,
                'getRound' => 0,
                'getReviewMethod' => '',
                'getRecommendation' => '',
                'getReviewerFullName' => '',
                'getId' => 0,
                'getDateResponseDue' => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
                'getDateAssigned' => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
                'getDateDue' => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(ReviewAssignment::class, $reviewAssignmentMock);
        
        $reviewAssignmentRepoMock = Mockery::mock(app(ReviewAssignmentRepository::class))
            ->makePartial()
            ->shouldReceive([
                'get' => $reviewAssignmentMock,
                'edit' => null,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(ReviewAssignmentRepository::class, $reviewAssignmentRepoMock);
        
        $this->assertNull($reviewReminderJob->handle());
    }

    /**
     * Test job will not fail
     */
    public function testRunSerializedJob(): void
    {
        Mail::fake();
        
        $reviewReminderJob = unserialize($this->serializedJobData);

        // need to mock request so that a valid context information is set and can be retrived
        $contextService = Services::get("context");
        $context = $contextService->get($reviewReminderJob->contextId);
        $this->mockRequest($context->getPath() . '/test-page/test-op');

        $publicationMock = Mockery::mock(\APP\publication\Publication::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('authors')
            ->andReturn(\Illuminate\Support\LazyCollection::make([new \PKP\author\Author()]))
            ->getMock();

        $submissionMock = Mockery::mock(\APP\submission\Submission::class)
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
                'getData' => 0,
                'getCurrentPublication' => $publicationMock
            ])
            ->withAnyArgs()
            ->getMock();

        $submissionRepoMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($submissionMock)
            ->getMock();

        app()->instance(SubmissionRepository::class, $submissionRepoMock);

        $emailTemplateMock = Mockery::mock(\PKP\emailTemplate\EmailTemplate::class)
            ->makePartial()
            ->shouldReceive([
                "getLocalizedData" => ""
            ])
            ->withAnyArgs()
            ->getMock();

        $emailTemplateRepoMock = Mockery::mock(app(EmailTemplateRepository::class))
            ->makePartial()
            ->shouldReceive([
                'getByKey' => $emailTemplateMock,
            ])
            ->withAnyArgs()
            ->getMock();

        app()->instance(EmailTemplateRepository::class, $emailTemplateRepoMock);

        $invitationRepoMock = Mockery::mock(app(InvitationRepository::class))
            ->makePartial()
            ->shouldReceive([
                'addInvitation' => 0,
                'getMailable' => null,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(InvitationRepository::class, $invitationRepoMock);

        $eventRepoMock = Mockery::mock(app(EventRepository::class))
            ->makePartial()
            ->shouldReceive([
                'newDataObject' => new \PKP\log\event\EventLogEntry,
                'add' => 0,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(EventRepository::class, $eventRepoMock);

        $this->assertNull($reviewReminderJob->handle());
    }
}
