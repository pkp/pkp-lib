<?php

/**
 * @file tests/jobs/metadata/MetadataChangedJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for changing metadata job.
 */

namespace PKP\tests\jobs\metadata;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
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
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:36:"PKP\\jobs\\metadata\\MetadataChangedJob":3:{s:15:"\0*\0submissionId";i:24;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [
            ...parent::getMockedDAOs(),
            $this->getAppSearchDaoKey(),
        ];
    }

    /**
     * @see PKPTestCase::getMockedContainerKeys()
     */
    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            SubmissionRepository::class,
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            MetadataChangedJob::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        $this->mockRequest();

        /** @var MetadataChangedJob $metadataChangedJob */
        $metadataChangedJob = unserialize($this->serializedJobData);

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

        DAORegistry::registerDAO($this->getAppSearchDaoKey(), $submissionSearchDAOMock);

        $this->assertNull($metadataChangedJob->handle());
    }
}
