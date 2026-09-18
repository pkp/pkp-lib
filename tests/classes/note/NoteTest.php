<?php

/**
 * @file tests/classes/note/NoteTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class NoteTest
 *
 * @brief Test class for the Note model.
 */

namespace PKP\tests\classes\note;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\note\Note;
use PKP\query\Query;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\PKPTestCase;

#[CoversClass(Note::class)]
class NoteTest extends PKPTestCase
{
    /**
     * Deleting a note deletes the files attached to it, so that they are not left
     * behind pointing at a note that no longer exists.
     */
    public function testDeletingANoteDeletesItsFiles(): void
    {
        [$query, $note, $submissionFileId, $fileId] = $this->createDiscussionWithAttachment();

        $note->delete();

        self::assertNull(Repo::submissionFile()->get($submissionFileId));
        self::assertSame(0, DB::table('files')->where('file_id', $fileId)->count());

        $query->delete();
    }

    /**
     * Deleting a discussion cascades through its notes to the files attached to them.
     */
    public function testDeletingADiscussionDeletesTheFilesAttachedToItsNotes(): void
    {
        [$query, $note, $submissionFileId, $fileId] = $this->createDiscussionWithAttachment();

        $query->delete();

        self::assertNull(Note::find($note->id));
        self::assertNull(Repo::submissionFile()->get($submissionFileId));
        self::assertSame(0, DB::table('files')->where('file_id', $fileId)->count());
    }

    /**
     * Build a discussion holding a single note with one file attached to it.
     *
     * @return array{0: Query, 1: Note, 2: int, 3: int} The discussion, its note, and the
     * ids of the submission file attached to that note and of its underlying file.
     */
    private function createDiscussionWithAttachment(): array
    {
        $submissionId = DB::table('submissions')->value('submission_id');
        $userId = DB::table('users')->value('user_id');
        $genreId = DB::table('genres')->value('genre_id');
        if (!$submissionId || !$userId || !$genreId) {
            self::markTestSkipped('The test database has no submission to attach a discussion to.');
        }

        $query = Query::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'stageId' => WORKFLOW_STAGE_ID_EDITING,
        ]);
        $note = Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'userId' => $userId,
        ]);

        // The file is never written to disk; only the record of it matters here.
        $fileId = DB::table('files')->insertGetId([
            'path' => 'tests/' . uniqid('note-attachment-') . '.pdf',
            'mimetype' => 'application/pdf',
        ], 'file_id');

        $submissionFileId = Repo::submissionFile()->add(
            Repo::submissionFile()->newDataObject([
                'fileId' => $fileId,
                'submissionId' => $submissionId,
                'uploaderUserId' => $userId,
                'genreId' => $genreId,
                'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
                'assocType' => Application::ASSOC_TYPE_NOTE,
                'assocId' => $note->id,
                'name' => ['en' => 'attachment.pdf'],
            ])
        );

        return [$query, $note, $submissionFileId, $fileId];
    }
}
