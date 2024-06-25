<?php

/**
 * @file tests/jobs/notifications/StatisticsReportMailTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for statistics report mail job.
 */

namespace PKP\tests\jobs\notifications;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use PKP\user\Repository as UserRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\jobs\notifications\StatisticsReportMail;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class StatisticsReportMailTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:43:"PKP\\jobs\\notifications\\StatisticsReportMail":6:{s:10:"\0*\0userIds";O:29:"Illuminate\Support\Collection":2:{s:8:"\0*\0items";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:6;}s:28:"\0*\0escapeWhenCastingToString";b:0;}s:12:"\0*\0contextId";i:1;s:12:"\0*\0dateStart";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"2024-05-01 00:00:00.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:10:"Asia/Dhaka";}s:10:"\0*\0dateEnd";O:17:"DateTimeImmutable":3:{s:4:"date";s:26:"2024-06-01 00:00:00.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:10:"Asia/Dhaka";}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return array_filter([
            ...parent::getMockedDAOs(),
            substr(strrchr(get_class(Application::getContextDAO()), '\\'), 1),
            'NotificationDAO',
            'NotificationSettingsDAO',
        ]);
    }

    /**
     * @see PKPTestCase::getMockedContainerKeys()
     */
    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            EmailTemplateRepository::class,
            UserRepository::class,
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            StatisticsReportMail::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var StatisticsReportMail $statisticsReportMailJob */
        $statisticsReportMailJob = unserialize($this->serializedJobData);

        $this->mockRequest();

        $this->mockMail();

        $contextDaoClass = get_class(Application::getContextDAO());

        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
                'getPrimaryLocale' => 'en',
                'getContactEmail' => 'testmail@mail.test',
                'getContactName' => 'Test User',
                'getLocalizedData' => '',
            ])
            ->withAnyArgs()
            ->getMock();

        $contextDaoMock = Mockery::mock($contextDaoClass)
            ->makePartial()
            ->shouldReceive('getById')
            ->withAnyArgs()
            ->andReturn($contextMock)
            ->getMock();

        DAORegistry::registerDAO(substr(strrchr($contextDaoClass, '\\'), 1), $contextDaoMock);

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

        // Need to replace the container binding of `editorialStats` with mock object
        \APP\core\Services::register(
            new class implements \Pimple\ServiceProviderInterface
            {
                public function register(\Pimple\Container $pimple)
                {
                    $pimple['editorialStats'] = Mockery::mock(\APP\services\StatsEditorialService::class)
                        ->makePartial()
                        ->shouldReceive([
                            'getOverview' => [
                                [
                                    'key' => 'submissionsReceived',
                                    'name' => 'stats.name.submissionsReceived',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsAccepted',
                                    'name' => 'stats.name.submissionsAccepted',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsDeclined',
                                    'name' => 'stats.name.submissionsDeclined',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsSkipped',
                                    'name' => 'stats.name.submissionsSkipped',
                                    'value' => 0,
                                ],
                            ],
                            'countSubmissionsReceived' => 0,
                        ])
                        ->withAnyArgs()
                        ->getMock();
                }
            }
        );

        $userMock = Mockery::mock(\PKP\user\User::class)
            ->makePartial()
            ->shouldReceive('getId')
            ->withAnyArgs()
            ->andReturn(0)
            ->getMock();

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($userMock)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $notificationMock = Mockery::mock(\APP\notification\Notification::class)
            ->makePartial()
            ->shouldReceive('setData')
            ->withAnyArgs()
            ->andReturn(null)
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

        $this->assertNull($statisticsReportMailJob->handle());
    }
}