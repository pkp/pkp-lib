<?php
/**
 * @file classes/submissionFile/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A repository to find and manage submissions.
 */

namespace APP\submissionFile;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use PKP\observers\events\SubmissionFileDeleted;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\submissionFile\Repository as BaseRepository;
use PKP\submissionFile\SubmissionFile;

class Repository extends BaseRepository
{
    /** @copydoc DAO::delete() */
    public function delete(SubmissionFile $submissionFile): void
    {
        $this->deleteRelatedSubmissionFileObjects($submissionFile);
        parent::delete($submissionFile);
    }

    public function add(SubmissionFile $submissionFile): int
    {
        $galley = null;

        if ($submissionFile->getData('assocType') === Application::ASSOC_TYPE_REPRESENTATION) {
            $galley = Repo::galley()->get($submissionFile->getData('assocId'));
            if (!$galley) {
                throw new Exception('Galley not found when adding submission file.');
            }
        }

        $submissionFileId = parent::add($submissionFile);

        if ($galley) {
            Repo::galley()->edit($galley, ['submissionFileId' => $submissionFile->getId()]);
        }

        return $submissionFileId;
    }

    /**
     * Delete related objects when a submission file is deleted
     */
    public function deleteRelatedSubmissionFileObjects(SubmissionFile $submissionFile): void
    {
        // Remove galley associations and update search index
        if ($submissionFile->getData('assocType') == Application::ASSOC_TYPE_REPRESENTATION) {
            $galley = Repo::galley()->get($submissionFile->getData('assocId'));
            if ($galley && $galley->getData('submissionFileId') == $submissionFile->getId()) {
                $galley->_data['submissionFileId'] = null; // Work around pkp/pkp-lib#5740
                Repo::galley()->edit($galley, []);
            }

            event(
                new SubmissionFileDeleted(
                    (int)$submissionFile->getData('submissionId'),
                    (int)$submissionFile->getId()
                )
            );
        }
    }

    /**
     * Allow authors to upload to galley file stages.
     *
     * More information at PKP\submissionFile\Repository::getAssignedFileStages
     *
     * @return array List of file stages (SUBMISSION_FILE_*)
     */
    public function getAssignedFileStages(
        array $stageAssignments,
        int $action
    ): array {
        $allowedFileStages = parent::getAssignedFileStages($stageAssignments, $action);

        if (array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $stageAssignments)
                && in_array(Role::ROLE_ID_AUTHOR, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION])) {
            $allowedFileStages[] = SubmissionFile::SUBMISSION_FILE_PROOF;
        }

        return $allowedFileStages;
    }

    public function getFileStages(): array
    {
        $stages = [
            SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            SubmissionFile::SUBMISSION_FILE_NOTE,
            SubmissionFile::SUBMISSION_FILE_PROOF,
            SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_DEPENDENT,
            SubmissionFile::SUBMISSION_FILE_QUERY,
        ];

        Hook::call('SubmissionFile::fileStages', [&$stages]);

        return $stages;
    }
}
