<?php

/**
 * @file tests/classes/citation/CitationDAOTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the events emitted by the citation DAO.
 */

namespace PKP\tests\classes\citation;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\LazyCollection;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\citation\Citation;
use PKP\citation\CitationDAO;
use PKP\observers\events\CitationMetadataChanged;
use PKP\observers\events\MetadataChanged;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(CitationDAO::class)]
class CitationDAOTest extends PKPTestCase
{
    protected const PUBLICATION_ID = 10;
    protected const CITATION_ID = 77;

    /**
     * A partial DAO whose persistence methods are stubbed out, so that only
     * the event emission is exercised.
     */
    protected function makeDao(?LazyCollection $existingCitations = null): CitationDAO
    {
        return Mockery::mock(CitationDAO::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('update')->withAnyArgs()->andReturnTrue()
            ->shouldReceive('getInsertId')->withAnyArgs()->andReturn(self::CITATION_ID)
            ->shouldReceive('_updateObjectMetadata')->withAnyArgs()->andReturnNull()
            ->shouldReceive('deleteById')->withAnyArgs()->andReturn(1)
            ->shouldReceive('getByPublicationId')
            ->withAnyArgs()
            ->andReturn($existingCitations ?? LazyCollection::make([]))
            ->getMock();
    }

    protected function makeCitation(): Citation
    {
        $citation = new Citation();
        $citation->setId(self::CITATION_ID);
        $citation->setData('publicationId', self::PUBLICATION_ID);
        $citation->setSequence(1);
        $citation->setRawCitation('Doe, J. (2020). A title.');

        return $citation;
    }

    public function testInsertEmitsCitationEvent(): void
    {
        $dao = $this->makeDao();

        Event::fake();

        $dao->insertObject($this->makeCitation());

        Event::assertDispatchedTimes(CitationMetadataChanged::class, 1);
        Event::assertNotDispatched(MetadataChanged::class);
    }

    public function testUpdateEmitsCitationEvent(): void
    {
        $dao = $this->makeDao();

        Event::fake();

        $dao->updateObject($this->makeCitation());

        Event::assertDispatchedTimes(CitationMetadataChanged::class, 1);
        Event::assertNotDispatched(MetadataChanged::class);
    }

    public function testDeleteObjectEmitsCitationEvent(): void
    {
        $dao = $this->makeDao();

        Event::fake();

        $dao->deleteObject($this->makeCitation());

        Event::assertDispatchedTimes(CitationMetadataChanged::class, 1);
    }

    /**
     * Deleting every citation of a publication is a single operation, so it
     * emits one event regardless of how many rows were removed.
     */
    public function testDeleteByPublicationEmitsOneEventForManyCitations(): void
    {
        $citations = LazyCollection::make([
            $this->makeCitation(),
            $this->makeCitation(),
            $this->makeCitation(),
        ]);

        $dao = $this->makeDao($citations);

        Event::fake();

        $dao->deleteByPublicationId(self::PUBLICATION_ID);

        Event::assertDispatchedTimes(CitationMetadataChanged::class, 1);
    }
}
