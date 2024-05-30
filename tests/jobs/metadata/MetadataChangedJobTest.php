<?php

namespace PKP\tests\jobs\metadata;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\metadata\MetadataChangedJob;
use APP\submission\Repository as SubmissionRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class MetadataChangedJobTest extends PKPTestCase
{
    /**
     * Serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'O:36:"PKP\jobs\metadata\MetadataChangedJob":3:{s:15:" * submissionId";i:24;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperDepositIssueJobInstance(): void
    {
        $this->assertInstanceOf(
            MetadataChangedJob::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        $this->mockRequest();

        /** @var MetadataChangedJob $metadataChangedJob */
        $metadataChangedJob = unserialize($this->serializedJobData);

        $publicationMock = Mockery::mock(\APP\publication\Publication::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('authors')
            ->andReturn(\Illuminate\Support\LazyCollection::make([new \PKP\author\Author()]))
            ->shouldReceive('getData') 
            ->with('subject')
            ->andReturn([])
            ->shouldReceive('getData')
            ->with('subjects')
            ->andReturn([])
            ->shouldReceive('getData')
            ->with('keywords')
            ->andReturn([])
            ->shouldReceive('getData')
            ->with('disciplines')
            ->andReturn([])
            ->getMock();

        $submissionMock = Mockery::mock(\APP\submission\Submission::class)
            ->makePartial()
            ->shouldReceive(['getCurrentPublication' => $publicationMock,])
            ->shouldReceive('getData')
            ->with('doiObject')
            ->andReturn(new \PKP\doi\Doi())
            ->getMock();

        $submissionMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($submissionMock)
            ->getMock();

        app()->instance(SubmissionRepository::class, $submissionMock);

        $submissionSearchDAOMock = Mockery::mock(\PKP\search\SubmissionSearchDAO::class)
            ->makePartial()
            ->shouldReceive(['insertObject' => 0, 'insertObjectKeywords' => null,])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('ArticleSearchDAO', $submissionSearchDAOMock);     // for OJS
        DAORegistry::registerDAO('MonographSearchDAO', $submissionSearchDAOMock);   // for OMP
        DAORegistry::registerDAO('PreprintSearchDAO', $submissionSearchDAOMock);    // for OPS

        $this->assertNull($metadataChangedJob->handle());
    }
}
