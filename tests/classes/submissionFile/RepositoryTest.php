<?php

/**
 * @file tests/classes/submissionFile/RepositoryTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class RepositoryTest
 *
 * @brief Test class for the submission file Repository.
 */

namespace PKP\tests\classes\submissionFile;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversMethod;
use PKP\note\Note;
use PKP\query\Query;
use PKP\submissionFile\Repository;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\PKPTestCase;

#[CoversMethod(Repository::class, 'getWorkflowStageId')]
class RepositoryTest extends PKPTestCase
{
    /**
     * A file attached to a discussion resolves to the stage that discussion belongs to.
     */
    public function testQueryFileResolvesToTheStageOfItsDiscussion(): void
    {
        $userId = DB::table('users')->value('user_id');
        if (!$userId) {
            self::markTestSkipped('No user available to own the test discussion note.');
        }

        $query = Query::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $this->getUnusedId('submissions', 'submission_id'),
            'stageId' => WORKFLOW_STAGE_ID_EDITING,
        ]);
        $note = Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'userId' => $userId,
        ]);

        $submissionFile = $this->newQueryFile(Application::ASSOC_TYPE_NOTE, $note->id);

        self::assertSame(
            WORKFLOW_STAGE_ID_EDITING,
            Repo::submissionFile()->getWorkflowStageId($submissionFile)
        );

        // Deleting the discussion takes its notes with it.
        $query->delete();
        self::assertNull(Note::find($note->id));
    }

    /**
     * A file left behind by a deleted discussion resolves to no stage rather than
     * raising an error.
     */
    public function testOrphanedQueryFileHasNoWorkflowStage(): void
    {
        $submissionFile = $this->newQueryFile(
            Application::ASSOC_TYPE_NOTE,
            $this->getUnusedId('notes', 'note_id')
        );

        self::assertNull(Repo::submissionFile()->getWorkflowStageId($submissionFile));
    }

    /**
     * A file at the discussion stage that was never associated with a note resolves
     * to no stage rather than raising an error.
     */
    public function testQueryFileWithoutANoteHasNoWorkflowStage(): void
    {
        $submissionFile = $this->newQueryFile(Application::ASSOC_TYPE_SUBMISSION, 1);

        self::assertNull(Repo::submissionFile()->getWorkflowStageId($submissionFile));
    }

    /**
     * Build an unsaved submission file at the discussion stage with the given association.
     */
    private function newQueryFile(int $assocType, int $assocId): SubmissionFile
    {
        return Repo::submissionFile()->newDataObject([
            'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
            'assocType' => $assocType,
            'assocId' => $assocId,
        ]);
    }

    /**
     * Get an id that is not in use, so that a record can be pointed at something
     * that does not exist.
     */
    private function getUnusedId(string $table, string $column): int
    {
        return ((int) DB::table($table)->max($column)) + 1000;
    }
}
