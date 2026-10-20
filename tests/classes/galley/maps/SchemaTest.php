<?php

/**
 * @file tests/classes/galley/maps/SchemaTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for galley revision counts.
 */

namespace PKP\tests\classes\galley\maps;

use APP\core\Request;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\submissionFile\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\context\Context;
use PKP\galley\Galley;
use PKP\galley\maps\Schema;
use PKP\services\PKPSchemaService;
use PKP\submission\Representation;
use PKP\tests\PKPTestCase;

#[CoversClass(Schema::class)]
class SchemaTest extends PKPTestCase
{
    protected function getMockedContainerKeys(): array
    {
        return [Repository::class];
    }

    #[DataProvider('revisionCountProvider')]
    public function testRevisionCount(bool $generated, int $revisions): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('getRevisions')
            ->with(123)
            ->willReturn(new Collection(range(1, $revisions)));
        app()->instance(Repository::class, $repository);

        $publication = $this->createMock(Publication::class);
        $publication->method('getLanguages')->willReturn(['en']);
        $context = $this->createMock(Context::class);
        $context->method('getSupportedSubmissionMetadataLocales')->willReturn(['en']);
        $schemaService = $this->createMock(PKPSchemaService::class);
        $schemaService->method('addMissingMultilingualValues')->willReturnArgument(1);

        $map = new Schema(
            $this->createMock(Submission::class),
            $publication,
            [],
            $this->createMock(Request::class),
            $context,
            $schemaService
        );
        $map->props = ['revisionCount'];

        $galley = new Galley();
        $galley->setData('label', 'PDF');
        $galley->setData('submissionFileId', 123);
        if ($generated) {
            $galley->setData('generatedFrom', Representation::GENERATED_FROM_EDITOR);
        }

        $output = $map->map($galley);
        $this->assertSame($revisions, $output['revisionCount']);
    }

    public static function revisionCountProvider(): array
    {
        return [
            'manual original' => [false, 1],
            'manual one revision' => [false, 2],
            'manual two revisions' => [false, 3],
            'generated original' => [true, 1],
            'generated one revision' => [true, 2],
            'generated two revisions' => [true, 3],
        ];
    }
}
