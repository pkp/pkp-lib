<?php

/**
 * @file tests/classes/author/RepositoryTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the events emitted by the author repository.
 */

namespace PKP\tests\classes\author;

use App\author\Author;
use PKP\author\DAO as AuthorDAO;
use PKP\author\Repository as AuthorRepository;
use APP\publication\Publication;
use APP\publication\Repository as PublicationRepository;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\observers\events\MetadataChanged;
use PKP\observers\events\PublicationMetadataChanged;
use PKP\tests\PKPTestCase;
use PKP\observers\events\AuthorMetadataChanged;

#[RunTestsInSeparateProcesses]
#[CoversClass(AuthorRepository::class)]
class RepositoryTest extends PKPTestCase
{
    protected const PUBLICATION_ID = 10;
    protected const AUTHOR_ID = 60;

    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            AuthorRepository::class,
            PublicationRepository::class,
        ];
    }

    /**
     * Register an author repository whose DAO never touches the database,
     * plus a publication repository returning a stub parent.
     */
    protected function registerRepositories(): AuthorRepository
    {
        $daoMock = Mockery::mock(AuthorDAO::class)
            ->makePartial()
            ->shouldReceive('update')
            ->withAnyArgs()
            ->andReturnNull()
            ->getMock();

        $repository = app(AuthorRepository::class);

        $storedAuthor = new Author();
        $storedAuthor->setData('id', self::AUTHOR_ID);
        $storedAuthor->setData('publicationId', self::PUBLICATION_ID);

        $daoMock = Mockery::mock(AuthorDAO::class)
            ->makePartial()
            ->shouldReceive('update')->withAnyArgs()->andReturnNull()
            ->shouldReceive('insert')->withAnyArgs()->andReturn(self::AUTHOR_ID)
            ->shouldReceive('delete')->withAnyArgs()->andReturnNull()
            ->shouldReceive('get')->withAnyArgs()->andReturn($storedAuthor)
            ->shouldReceive('getNextSeq')->withAnyArgs()->andReturn(0)
            ->shouldReceive('resetContributorsOrder')->withAnyArgs()->andReturnNull()
            ->getMock();

        $repository->dao = $daoMock;

        app()->instance(AuthorRepository::class, $repository);

        $publicationRepoMock = Mockery::mock(app(PublicationRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(Mockery::mock(Publication::class)->makePartial())
            ->getMock();

        app()->instance(PublicationRepository::class, $publicationRepoMock);

        return $repository;
    }

    protected function makeAuthors(int $count): array
    {
        $authors = [];
        for ($i = 0; $i < $count; $i++) {
            $author = new Author();
            $author->setData('publicationId', self::PUBLICATION_ID);
            $authors[] = $author;
        }
        return $authors;
    }

    /**
     * Reordering is a single operation on one publication, so it emits one
     * event regardless of how many authors were moved.
     */
    public function testReorderEmitsOneEventForAnyNumberOfAuthors(): void
    {
        $this->mockRequest();
        $repository = $this->registerRepositories();

        Event::fake();

        $repository->setAuthorsOrder(self::PUBLICATION_ID, $this->makeAuthors(5));

        Event::assertDispatchedTimes(PublicationMetadataChanged::class, 1);
    }

    /**
     * The cascade must not reuse MetadataChanged: that event is consumed by
     * the search reindexing listener, which never ran on author changes.
     */
    public function testReorderDoesNotEmitMetadataChanged(): void
    {
        $this->mockRequest();
        $repository = $this->registerRepositories();

        Event::fake();

        $repository->setAuthorsOrder(self::PUBLICATION_ID, $this->makeAuthors(2));

        Event::assertNotDispatched(MetadataChanged::class);
    }

    public function testAddEmitsAuthorEvent(): void
    {
        $this->mockRequest();
        $repository = $this->registerRepositories();

        Event::fake();

        $author = new Author();
        $author->setData('publicationId', self::PUBLICATION_ID);

        $repository->add($author);

        Event::assertDispatchedTimes(AuthorMetadataChanged::class, 1);
        Event::assertNotDispatched(MetadataChanged::class);
    }

    public function testEditEmitsAuthorEvent(): void
    {
        $this->mockRequest();
        $repository = $this->registerRepositories();

        Event::fake();

        $author = new Author();
        $author->setData('id', self::AUTHOR_ID);
        $author->setData('publicationId', self::PUBLICATION_ID);

        $repository->edit($author, ['familyName' => ['en' => 'Changed']]);

        Event::assertDispatchedTimes(AuthorMetadataChanged::class, 1);
        Event::assertNotDispatched(MetadataChanged::class);
    }

    public function testDeleteEmitsAuthorEvent(): void
    {
        $this->mockRequest();
        $repository = $this->registerRepositories();

        Event::fake();

        $author = new Author();
        $author->setData('id', self::AUTHOR_ID);
        $author->setData('publicationId', self::PUBLICATION_ID);

        $repository->delete($author);

        Event::assertDispatchedTimes(AuthorMetadataChanged::class, 1);
        Event::assertNotDispatched(MetadataChanged::class);
    }
}
