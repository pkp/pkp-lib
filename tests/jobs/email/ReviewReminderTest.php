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

namespace PKP\tests\jobs\email;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\email\ReviewReminder;
use Illuminate\Support\Facades\Mail;
use PKP\user\Repository as UserRepository;
use PKP\log\event\Repository as EventRepository;
use PKP\submission\reviewAssignment\ReviewAssignment;
use APP\submission\Repository as SubmissionRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\submission\reviewAssignment\Repository as ReviewAssignmentRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(ReviewReminder::class)]
class ReviewReminderTest extends PKPTestCase
{
    /**
     * Serializion from OJS 3.5.0
     */
    protected string $serializedJobData = <<<END
    O:29:"PKP\\jobs\\email\\ReviewReminder":5:{s:9:"contextId";i:1;s:18:"reviewAssignmentId";i:57;s:13:"mailableClass";s:43:"PKP\mail\mailables\ReviewResponseRemindAuto";s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            ReviewReminder::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var ReviewReminder $reviewReminderJob */
        $reviewReminderJob = unserialize($this->serializedJobData);

        // Fake the mail facade
        Mail::fake();        

        // need to mock request so that a valid context information is set and can be retrived
        $this->mockRequest();

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
        
        $reviewAssignmentRepoMock = Mockery::mock(app(ReviewAssignmentRepository::class))
            ->makePartial()
            ->shouldReceive([
                'get' => $reviewAssignmentMock,
                'edit' => null,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(ReviewAssignmentRepository::class, $reviewAssignmentRepoMock);

        $userMock = Mockery::mock(\PKP\user\User::class)
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
                'getFullName' => 'Test User',
            ])
            ->withAnyArgs()
            ->getMock();

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($userMock)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $contextMock = Mockery::mock(\PKP\context\Context::class)
            ->makePartial()
            ->shouldReceive([
                'getPath' => '',
                'getId' => 0,
            ])
            ->withAnyArgs()
            ->getMock();
        
        $contextServiceMock = Mockery::mock(\APP\services\ContextService::class)
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($contextMock)
            ->getMock();
        
        app()->instance('context', $contextServiceMock);

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
                'getLocalizedData' => '',
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

        $eventRepoMock = Mockery::mock(app(EventRepository::class))
            ->makePartial()
            ->shouldReceive([
                'newDataObject' => new \PKP\log\event\EventLogEntry,
                'add' => 0,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(EventRepository::class, $eventRepoMock);

        $reviewReminderJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
