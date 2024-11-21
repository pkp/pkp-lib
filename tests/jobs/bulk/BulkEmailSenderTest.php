<?php

/**
 * @file tests/jobs/bulk/BulkEmailSenderTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for bulk email sending job.
 */

namespace PKP\tests\jobs\bulk;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\bulk\BulkEmailSender;
use PKP\user\Collector as UserCollector;
use PKP\user\Repository as UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[CoversClass(BulkEmailSender::class)]
class BulkEmailSenderTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:29:"PKP\\jobs\\bulk\BulkEmailSender":9:{s:10:"\0*\0userIds";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}s:12:"\0*\0contextId";i:1;s:10:"\0*\0subject";s:12:"Test subject";s:7:"\0*\0body";s:16:"<p>Test body</p>";s:12:"\0*\0fromEmail";s:20:"rvaca@mailinator.com";s:11:"\0*\0fromName";s:11:"Ramiro Vaca";s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";s:7:"batchId";s:36:"9c1cbc05-017b-4a02-bd5a-b113c92a7735";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            BulkEmailSender::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        $this->mockMail();
        
        /** @var BulkEmailSender $bulkEmailSenderJob*/
        $bulkEmailSenderJob = unserialize($this->serializedJobData);

        $userCollectorMock = Mockery::mock(app(UserCollector::class))
            ->makePartial()
            ->shouldReceive('getMany')
            ->withAnyArgs()
            ->andReturn(\Illuminate\Support\LazyCollection::make([new \PKP\user\User]))
            ->getMock();
        
        app()->instance(UserCollector::class, $userCollectorMock);

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('getCollector')
            ->withAnyArgs()
            ->andReturn($userCollectorMock)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $bulkEmailSenderJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
