<?php

/**
 * @file tests/jobs/notifications/NewAnnouncementNotifyUsersTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for new announcement notification ot users job.
 */

namespace PKP\tests\jobs\notifications;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
use Carbon\Carbon;
use PKP\tests\DatabaseTestCase;
use PKP\user\Repository as UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\jobs\notifications\NewAnnouncementNotifyUsers;
use PKP\announcement\Repository as AnnouncementRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\announcement\Announcement;

#[RunTestsInSeparateProcesses]
#[CoversClass(NewAnnouncementNotifyUsers::class)]
class NewAnnouncementNotifyUsersTest extends DatabaseTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:49:"PKP\\jobs\\notifications\\NewAnnouncementNotifyUsers":7:{s:15:"\0*\0recipientIds";O:29:"Illuminate\Support\Collection":2:{s:8:"\0*\0items";a:3:{i:0;i:2;i:1;i:3;i:2;i:4;}s:28:"\0*\0escapeWhenCastingToString";b:0;}s:12:"\0*\0contextId";i:1;s:17:"\0*\0announcementId";i:1;s:9:"\0*\0locale";s:2:"en";s:9:"\0*\0sender";O:13:"PKP\user\User":7:{s:5:"_data";a:22:{s:2:"id";i:1;s:8:"userName";s:5:"admin";s:8:"password";s:60:"$2y$10\$uFmYXg8/Ufa0HbskyW57Be22stFGY5qtxJZmTOae3PfDB86V3x7BW";s:5:"email";s:23:"pkpadmin@mailinator.com";s:3:"url";N;s:5:"phone";N;s:14:"mailingAddress";N;s:14:"billingAddress";N;s:7:"country";N;s:7:"locales";a:0:{}s:6:"gossip";N;s:13:"dateLastEmail";N;s:14:"dateRegistered";s:19:"2023-02-28 20:19:07";s:13:"dateValidated";N;s:13:"dateLastLogin";s:19:"2024-05-22 19:05:03";s:18:"mustChangePassword";N;s:7:"authStr";N;s:8:"disabled";b:0;s:14:"disabledReason";N;s:10:"inlineHelp";b:1;s:10:"familyName";a:1:{s:2:"en";s:5:"admin";}s:9:"givenName";a:1:{s:2:"en";s:5:"admin";}}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;s:9:"\0*\0_roles";a:0:{}}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see \PKP\tests\DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables(): array
    {
        return [
            'notifications'
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            NewAnnouncementNotifyUsers::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        $this->mockMail();
        
        $this->mockRequest();

        /** @var NewAnnouncementNotifyUsers $newAnnouncementNotifyUsersJob */
        $newAnnouncementNotifyUsersJob = unserialize($this->serializedJobData);

        $dummyAnnouncementInstance = new Announcement;
        $dummyAnnouncementInstance->id = 1;
        $dummyAnnouncementInstance->assocType = 256;
        $dummyAnnouncementInstance->assocId = 1;
        $dummyAnnouncementInstance->datePosted = Carbon::now()
            ->timestamp("y-m-d H:i:s")
            ->__toString();
        $dummyAnnouncementInstance->description = [
            "en" => "<p>Dummy Announcement</p>",
            "fr_CA" => "<p>Dummy Announcement</p>",
        ];
        $dummyAnnouncementInstance->descriptionShort = [
            "en" => "<p>Dummy Announcement</p>",
            "fr_CA" => "<p>Dummy Announcement</p>",
        ];
        $dummyAnnouncementInstance->title = [
            "en" => "Dummy Announcement",
            "fr_CA" => "Dummy Announcement 101",
        ];

        $announcementMock = Mockery::mock(\PKP\announcement\Announcement::class)
            ->makePartial()
            ->shouldReceive('find')
            ->withAnyArgs()
            ->andReturn($dummyAnnouncementInstance)
            ->getMock();

        app()->instance(\PKP\announcement\Announcement::class, $announcementMock);

        $contextDaoClass = get_class(Application::getContextDAO());

        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive([
                'getId' => 1,
                'getData' => '',
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
        
        DAORegistry::registerDAO(
            match (Application::get()->getName()) {
                'ojs2' => 'JournalDAO',
                'omp' => 'PressDAO',
                'ops' => 'ServerDAO',
            },
            $contextDaoMock
        );

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

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(new \PKP\user\User)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $newAnnouncementNotifyUsersJob->handle();
        
        $this->expectNotToPerformAssertions();

        app()->forgetInstance(\PKP\announcement\Announcement::class);
    }
}
