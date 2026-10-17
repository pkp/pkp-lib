<?php

/**
 * @file tests/classes/note/NoteTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoteTest
 *
 * @ingroup tests_classes_note
 *
 * @see Note
 *
 * @brief Tests that files attached to notes are removed together with the notes.
 */

namespace PKP\tests\classes\note;

use PKP\submissionFile\Collector;
use APP\submissionFile\Repository as SubmissionFileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\PKPApplication;
use PKP\note\Note;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(Note::class)]
class NoteTest extends DatabaseTestCase
{
    /**
     * @see \PKP\tests\DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables(): array
    {
        return ['notes'];
    }

    /**
     * Replace the submission file repository with a mock that returns the given
     * files for any note and records which files were deleted
     *
     * @param SubmissionFile[] $attachedFiles
     */
    protected function mockSubmissionFileRepository(array $attachedFiles, int $expectedDeletions): void
    {
        $collector = Mockery::mock(Collector::class);
        $collector->shouldReceive('filterByAssoc')
            ->with(PKPApplication::ASSOC_TYPE_NOTE, Mockery::type('array'))
            ->andReturnSelf();
        $collector->shouldReceive('getMany')
            ->andReturn(LazyCollection::make($attachedFiles));

        $repository = Mockery::mock(app(SubmissionFileRepository::class))->makePartial();
        $repository->shouldReceive('getCollector')->andReturn($collector);
        $repository->shouldReceive('delete')->times($expectedDeletions);

        app()->instance(SubmissionFileRepository::class, $repository);
    }

    protected function createNote(int $assocType, int $assocId): Note
    {
        $noteId = DB::table('notes')->insertGetId([
            'assoc_type' => $assocType,
            'assoc_id' => $assocId,
            'date_created' => now(),
            'date_modified' => now(),
        ], 'note_id');

        return Note::find($noteId);
    }

    /**
     * Deleting a note deletes the submission files attached to it
     */
    public function testDeletingNoteDeletesAttachedFiles(): void
    {
        $note = $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, 1);
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], 1);

        $note->delete();

        $this->assertNull(Note::find($note->id));
    }
}
