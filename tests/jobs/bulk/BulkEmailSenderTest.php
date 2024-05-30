<?php

namespace PKP\tests\jobs\bulk;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\bulk\BulkEmailSender;
use Illuminate\Support\Facades\Mail;
use PKP\user\Collector as UserCollector;
use APP\user\Repository as UserRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class BulkEmailSenderTest extends PKPTestCase
{
    /**
     * Serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'O:29:"PKP\jobs\bulk\BulkEmailSender":9:{s:10:" * userIds";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}s:12:" * contextId";i:1;s:10:" * subject";s:12:"Test subject";s:7:" * body";s:16:"<p>Test body</p>";s:12:" * fromEmail";s:20:"rvaca@mailinator.com";s:11:" * fromName";s:11:"Ramiro Vaca";s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";s:7:"batchId";s:36:"9c1cbc05-017b-4a02-bd5a-b113c92a7735";}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperDepositIssueJobInstance(): void
    {
        $this->assertInstanceOf(BulkEmailSender::class, unserialize($this->serializedJobData));
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        Mail::fake();
        
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

        $this->assertNull($bulkEmailSenderJob->handle());
    }
}
