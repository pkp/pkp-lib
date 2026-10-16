<?php

/**
 * @file tests/classes/galley/maps/SchemaTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for galley revision labels.
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

    #[DataProvider('revisionLabelProvider')]
    public function testRevisionLabel(bool $generated, int $revisionNumber, bool $showLabel): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('getRevisions')
            ->with(123)
            ->willReturn(new Collection(range(0, $revisionNumber)));
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
        $map->props = ['revisionLabel'];

        $galley = new Galley();
        $galley->setData('label', 'PDF');
        $galley->setData('submissionFileId', 123);
        if ($generated) {
            $galley->setData('generatedFrom', Representation::GENERATED_FROM_EDITOR);
        }

        $output = $map->map($galley);
        $this->assertSame(
            $showLabel ? __('galley.revision', ['revision' => $revisionNumber]) : '',
            $output['revisionLabel']
        );
    }

    public static function revisionLabelProvider(): array
    {
        return [
            'manual PDF original' => [false, 0, false],
            'manual PDF revision 1' => [false, 1, false],
            'manual PDF revision 2' => [false, 2, true],
            'manual PDF revision 3' => [false, 3, true],
            'generated PDF original' => [true, 0, false],
            'generated PDF revision 1' => [true, 1, false],
            'generated PDF revision 2' => [true, 2, true],
            'generated PDF revision 3' => [true, 3, true],
        ];
    }
}
