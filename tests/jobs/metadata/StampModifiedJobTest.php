<?php

/**
 * @file tests/jobs/metadata/StampModifiedJobTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the last_modified cascade job.
 */

namespace PKP\tests\jobs\metadata;

use APP\publication\Publication;
use APP\publication\Repository as PublicationRepository;
use APP\submission\Repository as SubmissionRepository;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\job\exceptions\JobException;
use PKP\jobs\metadata\StampModifiedJob;
use PKP\observers\events\PublicationMetadataChanged;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(StampModifiedJob::class)]
class StampModifiedJobTest extends PKPTestCase
{
    protected const PUBLICATION_ID = 10;
    protected const SUBMISSION_ID = 52;

    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            PublicationRepository::class,
            SubmissionRepository::class,
        ];
    }

    /**
     * A publication-level event stamps the parent submission.
     */
    public function testStampsSubmissionFromPublication(): void
    {
        $submissionRepoMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive('stampModified')
            ->once()
            ->with(self::SUBMISSION_ID)
            ->andReturnNull()
            ->getMock();

        app()->instance(SubmissionRepository::class, $submissionRepoMock);

        (new StampModifiedJob(StampModifiedJob::FROM_PUBLICATION, self::SUBMISSION_ID))->handle();

        $this->expectNotToPerformAssertions();
    }

    /**
     * An author-level event emits the next event in the chain rather than
     * stamping anything itself.
     */
    public function testEmitsPublicationEventFromAuthor(): void
    {
        Event::fake();

        $publicationMock = Mockery::mock(Publication::class)->makePartial();

        $publicationRepoMock = Mockery::mock(app(PublicationRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->with(self::PUBLICATION_ID)
            ->andReturn($publicationMock)
            ->getMock();

        app()->instance(PublicationRepository::class, $publicationRepoMock);

        (new StampModifiedJob(StampModifiedJob::FROM_AUTHOR, self::PUBLICATION_ID))->handle();

        Event::assertDispatched(PublicationMetadataChanged::class);
    }

    /**
     * A citation-level event behaves like an author-level one.
     */
    public function testEmitsPublicationEventFromCitation(): void
    {
        Event::fake();

        $publicationMock = Mockery::mock(Publication::class)->makePartial();

        $publicationRepoMock = Mockery::mock(app(PublicationRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->with(self::PUBLICATION_ID)
            ->andReturn($publicationMock)
            ->getMock();

        app()->instance(PublicationRepository::class, $publicationRepoMock);

        (new StampModifiedJob(StampModifiedJob::FROM_CITATION, self::PUBLICATION_ID))->handle();

        Event::assertDispatched(PublicationMetadataChanged::class);
    }

    /**
     * A missing parent aborts the cascade.
     */
    public function testThrowsWhenPublicationIsMissing(): void
    {
        $publicationRepoMock = Mockery::mock(app(PublicationRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturnNull()
            ->getMock();

        app()->instance(PublicationRepository::class, $publicationRepoMock);

        $this->expectException(JobException::class);

        (new StampModifiedJob(StampModifiedJob::FROM_AUTHOR, self::PUBLICATION_ID))->handle();
    }
}
