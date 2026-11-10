<?php

/**
 * @file tests/classes/publication/PlainLanguageSummaryValidationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PlainLanguageSummaryValidationTest
 *
 * @brief Tests that a required plain language summary is validated on publication edits (pkp/pkp-lib#13584).
 */

namespace PKP\tests\classes\publication;

use APP\core\Request;
use APP\publication\DAO;
use APP\publication\Publication;
use APP\submission\Submission;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\context\Context;
use PKP\publication\Repository;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;

#[CoversClass(Repository::class)]
class PlainLanguageSummaryValidationTest extends PKPTestCase
{
    private Repository $repository;
    private Publication $publication;
    private Submission $submission;
    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequest();

        $schemaService = new PKPSchemaService();
        $dao = new DAO($schemaService);
        $this->repository = new class ($dao, Mockery::mock(Request::class), $schemaService) extends Repository {
            protected function setStatusOnPublish(Publication $publication)
            {
            }

            public function createDois(Publication $publication): array
            {
                return [];
            }
        };

        $this->publication = $dao->newDataObject();
        $this->publication->setData('plainLanguageSummary', 'Stored summary', 'en');

        $this->submission = new Submission();
        $this->submission->setData('locale', 'en');
        $this->submission->setData('submissionProgress', '');
        $this->submission->setData('publications', collect([$this->publication]));

        $this->context = Mockery::mock(Context::class);
        $this->context->shouldReceive('getSupportedSubmissionMetadataLocales')->andReturn(['en']);
        $this->context->shouldReceive('getData')->with('plainLanguageSummary')->andReturn(Context::METADATA_REQUIRE);
    }

    public function testEditWithoutSummaryPasses(): void
    {
        $errors = $this->repository->validate($this->publication, ['pages' => '1-10'], $this->submission, $this->context);
        $this->assertArrayNotHasKey('plainLanguageSummary', $errors);
    }

    public function testEditEmptyingSummaryFails(): void
    {
        $errors = $this->repository->validate($this->publication, ['plainLanguageSummary' => ['en' => '']], $this->submission, $this->context);
        $this->assertArrayHasKey('plainLanguageSummary', $errors);
    }

    public function testEditEmptyingSummaryPassesWhileInProgress(): void
    {
        $this->submission->setData('submissionProgress', 'details');
        $errors = $this->repository->validate($this->publication, ['plainLanguageSummary' => ['en' => '']], $this->submission, $this->context);
        $this->assertArrayNotHasKey('plainLanguageSummary', $errors);
    }

    public function testNewPublicationWithoutSummaryFails(): void
    {
        $errors = $this->repository->validate(null, ['version' => 2], $this->submission, $this->context);
        $this->assertArrayHasKey('plainLanguageSummary', $errors);
    }
}
