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
use PKP\editorialTask\EditorialTask;
use PKP\note\Note;
use PKP\submissionFile\Collector;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\DatabaseTestCase;

#[CoversClass(Note::class)]
#[CoversClass(EditorialTask::class)]
class NoteTest extends DatabaseTestCase
{
    /** An ID that is not used by any submission or editorial task */
    private const UNUSED_ID = 999999;

    private SubmissionFileRepository $originalSubmissionFileRepository;

    /**
     * @see \PKP\tests\DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables(): array
    {
        return ['notes', 'edit_tasks', 'notifications'];
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

    protected function createEditorialTask(int $assocType, int $assocId): int
    {
        return DB::table('edit_tasks')->insertGetId([
            'assoc_type' => $assocType,
            'assoc_id' => $assocId,
            'stage_id' => WORKFLOW_STAGE_ID_SUBMISSION,
        ], 'edit_task_id');
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
     * Deleting an editorial task deletes its notes and the files attached to them
     */
    public function testDeletingEditorialTaskDeletesNotesAndAttachedFiles(): void
    {
        $taskId = $this->createEditorialTask(PKPApplication::ASSOC_TYPE_SUBMISSION, self::UNUSED_ID);
        $notes = [
            $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $taskId),
            $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $taskId),
        ];
        // One file per note
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], count($notes));

        EditorialTask::find($taskId)->delete();

        foreach ($notes as $note) {
            $this->assertNull(Note::find($note->id));
        }
    }

    /**
     * Deleting the editorial tasks of a submission deletes their notes, files and notifications,
     * and leaves alone the records of other types that happen to share an assoc ID
     */
    public function testDeleteBySubmissionIdOnlyDeletesRecordsOfTheEditorialTasks(): void
    {
        $taskId = $this->createEditorialTask(PKPApplication::ASSOC_TYPE_SUBMISSION, self::UNUSED_ID);
        $taskNote = $this->createNote(PKPApplication::ASSOC_TYPE_QUERY, $taskId);
        $unrelatedNote = $this->createNote(PKPApplication::ASSOC_TYPE_SUBMISSION, $taskId);
        $taskNotificationId = $this->createNotification(PKPApplication::ASSOC_TYPE_QUERY, $taskId);
        $unrelatedNotificationId = $this->createNotification(PKPApplication::ASSOC_TYPE_SUBMISSION, $taskId);
        // Only the file of the task note is deleted
        $this->mockSubmissionFileRepository([Mockery::mock(SubmissionFile::class)], 1);

        Repo::editorialTask()->deleteBySubmissionId(self::UNUSED_ID);

        $this->assertNull(EditorialTask::find($taskId));
        $this->assertNull(Note::find($taskNote->id));
        $this->assertNotNull(Note::find($unrelatedNote->id));
        $this->assertFalse(DB::table('notifications')->where('notification_id', $taskNotificationId)->exists());
        $this->assertTrue(DB::table('notifications')->where('notification_id', $unrelatedNotificationId)->exists());
    }
}
