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

use APP\facades\Repo;
use APP\submissionFile\Repository as SubmissionFileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\core\PKPApplication;
use PKP\note\Note;
use PKP\query\Query;
use PKP\submissionFile\Collector;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;

#[CoversClass(Note::class)]
#[CoversClass(Query::class)]
class NoteTest extends DatabaseTestCase
{
    /** An ID that is not used by any submission or query */
    private const UNUSED_ID = 999999;

    private SubmissionFileRepository $originalSubmissionFileRepository;

    /**
     * @see \PKP\tests\DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables(): array
    {
        return ['notes', 'queries', 'notifications'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSubmissionFileRepository = app(SubmissionFileRepository::class);
    }

    protected function tearDown(): void
    {
        // Put the real repository back, so that it is not mocked by the next test
        app()->instance(SubmissionFileRepository::class, $this->originalSubmissionFileRepository);
        parent::tearDown();
    }

    /**
     * Replace the submission file repository with a mock that returns the given
     * files for any note and expects the given number of file deletions,
     * which Mockery verifies when the test is torn down
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
            ->andReturnUsing(fn () => LazyCollection::make($attachedFiles));

        $repository = Mockery::mock($this->originalSubmissionFileRepository)->makePartial();
        $repository->shouldReceive('getCollector')->andReturn($collector);
        $repository->shouldReceive('delete')->times($expectedDeletions);

        app()->instance(SubmissionFileRepository::class, $repository);
    }

    protected function createQuery(int $assocType, int $assocId): int
    {
        return DB::table('queries')->insertGetId([
            'assoc_type' => $assocType,
            'assoc_id' => $assocId,
            'stage_id' => WORKFLOW_STAGE_ID_SUBMISSION,
        ], 'query_id');
    }

    protected function createNote(int $assocType, int $assocId): Note
    {
        $noteId = DB::table('notes')->insertGetId([
            'assoc_type' => $assocType,
            'assoc_id' => $assocId,
            'user_id' => DB::table('users')->value('user_id'),
            'date_created' => now(),
            'date_modified' => now(),
        ], 'note_id');

        return Note::find($noteId);
    }

    protected function createNotification(int $assocType, int $assocId): int
    {
        return DB::table('notifications')->insertGetId([
            'assoc_type' => $assocType,
            'assoc_id' => $assocId,
            'level' => 1,
            'type' => 1,
            'date_created' => now(),
        ], 'notification_id');
    }

    /**
     * Deleting a note deletes the submission files attached to it
     */
    public function testDeletingNoteDeletesAttachedFiles(): void
    {
        $note = $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, self::UNUSED_ID);
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], 1);

        $note->delete();

        $this->assertNull(Note::find($note->id));
    }

    /**
     * Deleting a note without attached files only deletes the note
     */
    public function testDeletingNoteWithoutFilesDeletesOnlyTheNote(): void
    {
        $note = $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, self::UNUSED_ID);
        $this->mockSubmissionFileRepository([], 0);

        $note->delete();

        $this->assertNull(Note::find($note->id));
    }

    /**
     * Deleting a query deletes its notes and the files attached to them
     */
    public function testDeletingQueryDeletesNotesAndAttachedFiles(): void
    {
        $queryId = $this->createQuery(PKPApplication::ASSOC_TYPE_SUBMISSION, self::UNUSED_ID);
        $notes = [
            $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $queryId),
            $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $queryId),
        ];
        // One file per note
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], count($notes));

        Query::find($queryId)->delete();

        foreach ($notes as $note) {
            $this->assertNull(Note::find($note->id));
        }
    }

    /**
     * Deleting the queries of a submission deletes their notes, files and notifications,
     * and leaves alone the records of other types that happen to share an assoc ID
     */
    public function testDeleteBySubmissionIdOnlyDeletesRecordsOfTheQueries(): void
    {
        $queryId = $this->createQuery(PKPApplication::ASSOC_TYPE_SUBMISSION, self::UNUSED_ID);
        $queryNote = $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $queryId);
        $unrelatedNote = $this->createNote(PKPApplication::ASSOC_TYPE_SUBMISSION, $queryId);
        $queryNotificationId = $this->createNotification(PKPApplication::ASSOC_TYPE_QUERY, $queryId);
        $unrelatedNotificationId = $this->createNotification(PKPApplication::ASSOC_TYPE_SUBMISSION, $queryId);
        // Only the file of the query note is deleted
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], 1);

        Repo::query()->deleteBySubmissionId(self::UNUSED_ID);

        $this->assertNull(Query::find($queryId));
        $this->assertNull(Note::find($queryNote->id));
        $this->assertNotNull(Note::find($unrelatedNote->id));
        $this->assertFalse(DB::table('notifications')->where('notification_id', $queryNotificationId)->exists());
        $this->assertTrue(DB::table('notifications')->where('notification_id', $unrelatedNotificationId)->exists());
    }
}
