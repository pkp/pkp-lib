<?php

namespace PKP\tests\jobs\metadata;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\metadata\BatchMetadataChangedJob;
use APP\submission\Repository as SubmissionRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class BatchMetadataChangedJobTest extends PKPTestCase
{
    /**
     * Serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'O:41:"PKP\jobs\metadata\BatchMetadataChangedJob":3:{s:13:"submissionIds";a:2:{i:0;i:1;i:1;i:2;}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetJobInstance(): void
    {
        $this->assertInstanceOf(
            BatchMetadataChangedJob::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        $this->mockRequest();

        /** @var BatchMetadataChangedJob $batchMetadataChangedJob */
        $batchMetadataChangedJob = unserialize($this->serializedJobData);

        /**
         * @disregard P1013 PHP Intelephense error suppression
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
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

        $submissionRepoMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($submissionMock)
            ->getMock();

        app()->instance(SubmissionRepository::class, $submissionRepoMock);

        $submissionSearchDAOMock = Mockery::mock(\PKP\search\SubmissionSearchDAO::class)
            ->makePartial()
            ->shouldReceive(['insertObject' => 0, 'insertObjectKeywords' => null,])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('ArticleSearchDAO', $submissionSearchDAOMock);     // for OJS
        DAORegistry::registerDAO('MonographSearchDAO', $submissionSearchDAOMock);   // for OMP
        DAORegistry::registerDAO('PreprintSearchDAO', $submissionSearchDAOMock);    // for OPS

        $this->assertNull($batchMetadataChangedJob->handle());
    }
}
