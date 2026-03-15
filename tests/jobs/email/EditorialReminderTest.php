<?php

/**
 * @file tests/jobs/email/EditorialReminderTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for editorial reminder job.
 */

namespace PKP\tests\jobs\email;

use Mockery;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\tests\PKPTestCase;
use PKP\jobs\email\EditorialReminder;
use PKP\user\Repository as UserRepository;
use PKP\submission\reviewRound\ReviewRound;
use APP\submission\Collector as SubmissionCollector;
use APP\submission\Repository as SubmissionRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class EditorialReminderTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:32:"PKP\\jobs\\email\\EditorialReminder":4:{s:11:"\0*\0editorId";i:2;s:12:"\0*\0contextId";i:1;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [
            ...parent::getMockedDAOs(),
            'NotificationSubscriptionSettingsDAO',
            'ReviewRoundDAO',
            'NotificationDAO',
            'NotificationSettingsDAO',
        ];
    }

    /**
     * @see PKPTestCase::getMockedContainerKeys()
     */
    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            UserRepository::class,
            SubmissionCollector::class,
            SubmissionRepository::class,
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            EditorialReminder::class, 
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
        $this->assertIsInt($job->getContextId());
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        $this->mockRequest();

        $this->mockMail();
        
        /** @var EditorialReminder $editorialReminderJob*/
        $editorialReminderJob = unserialize($this->serializedJobData);

        $notificationSubscriptionSettingsDAO = Mockery::mock(
                \PKP\notification\NotificationSubscriptionSettingsDAO::class
            )
            ->makePartial()
            ->shouldReceive('getNotificationSubscriptionSettings')
            ->withAnyArgs()
            ->andReturn([])
            ->getMock();

        DAORegistry::registerDAO(
            'NotificationSubscriptionSettingsDAO',
            $notificationSubscriptionSettingsDAO
        );

        // Need to replace the container binding of `context` with a mock object
        \APP\core\Services::register(
            new class implements \Pimple\ServiceProviderInterface
            {
                public function register(\Pimple\Container $pimple)
                {
                    $pimple['context'] = Mockery::mock(\APP\services\ContextService::class)
                        ->makePartial()
                        ->shouldReceive('get')
                        ->withAnyArgs()
                        ->andReturn(
                            // Mock the context(Journal/Press/Server) object
                            Mockery::mock(\PKP\context\Context::class)
                                ->makePartial()
                                ->shouldReceive([
                                    'getPath' => '',
                                    'getId' => 0,
                                    'getContactEmail' => 'testmail@test.com',
                                    'getLocalizedName' => 'Test Context',
                                    'getPrimaryLocale' => 'en',
                                    'getSupportedLocales' => ['en', 'fr_CA'],
                                ])
                                ->withAnyArgs()
                                ->getMock()
                        )
                        ->getMock();
                }
            }
        );

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

        /**
         * @disregard P1013 PHP Intelephense error suppression
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
        Locale::shouldReceive('getLocale')
            ->withAnyArgs()
            ->andReturn('en')
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn('')
            ->shouldReceive('setLocale')
            ->withAnyArgs()
            ->andReturn(null);

        /**
         * @disregard P1013 PHP Intelephense error suppression
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
        $submissionCollectorMock = Mockery::mock(app(SubmissionCollector::class))
            ->makePartial()
            ->shouldReceive('assignedTo')
            ->withAnyArgs()
            ->andReturnSelf()
            ->shouldReceive('filterByContextIds')
            ->withAnyArgs()
            ->andReturnSelf()
            ->shouldReceive('filterByStatus')
            ->withAnyArgs()
            ->andReturnSelf()
            ->shouldReceive('filterByIncomplete')
            ->withAnyArgs()
            ->andReturnSelf()
            ->shouldReceive('getIds')
            ->withAnyArgs()
            ->andReturn(collect([1,2]))
            ->getMock();
        
        app()->instance(SubmissionCollector::class, $submissionCollectorMock);

        $publicationMock = Mockery::mock(\APP\publication\Publication::class)
            ->makePartial()
            ->shouldReceive([
                'getLocalizedFullTitle' => 'Submission Full Title',
                'getShortAuthorString' => 'Author',
            ])
            ->withAnyArgs()
            ->getMock();

        $submissionMock = Mockery::mock(\APP\submission\Submission::class)
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
                'getCurrentPublication' => $publicationMock,
            ])
            ->shouldReceive('getData')
            ->with('stageId')
            ->andReturn(WORKFLOW_STAGE_ID_INTERNAL_REVIEW)
            ->getMock();

        $submissionRepoMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive([
                'get' => $submissionMock,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance(SubmissionRepository::class, $submissionRepoMock);

        $reviewRoundMock = Mockery::mock(\PKP\submission\reviewRound\ReviewRound::class)
            ->makePartial()
            ->shouldReceive([
                'getStatus' => ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS,
            ])
            ->withAnyArgs()
            ->getMock();

        $reviewRoundDaoMock = Mockery::mock(\PKP\submission\reviewRound\ReviewRoundDAO::class)
            ->makePartial()
            ->shouldReceive('getLastReviewRoundBySubmissionId')
            ->withAnyArgs()
            ->andReturn($reviewRoundMock)
            ->getMock();

        DAORegistry::registerDAO('ReviewRoundDAO', $reviewRoundDaoMock);

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

        $notificationMock = Mockery::mock(\APP\notification\Notification::class)
            ->makePartial()
            ->shouldReceive([
                'setData' => null,
                'getContextId' => 0,
            ])
            ->withAnyArgs()
            ->getMock();
        
        $notifiactionDaoMock = Mockery::mock(\PKP\notification\NotificationDAO::class)
            ->makePartial()
            ->shouldReceive([
                'newDataObject' => $notificationMock,
                'insertObject' => 0,
            ])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('NotificationDAO', $notifiactionDaoMock);

        $notificationSettingsDaoMock = Mockery::mock(\PKP\notification\NotificationSettingsDAO::class)
            ->makePartial()
            ->shouldReceive('updateNotificationSetting')
            ->withAnyArgs()
            ->andReturn(null)
            ->getMock();
        
        DAORegistry::registerDAO('NotificationSettingsDAO', $notificationSettingsDaoMock);

        $this->assertNull($editorialReminderJob->handle());
    }
}
