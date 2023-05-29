<?php

/**
 * @file controllers/grid/files/final/ManageFinalDraftFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageFinalDraftFilesGridHandler
 *
 * @ingroup controllers_grid_files_final
 *
 * @brief Handle the editor review file selection grid (selects which files to send to review or to next review round)
 */

namespace PKP\controllers\grid\files\final;

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\final\form\ManageFinalDraftFilesForm;
use PKP\controllers\grid\files\SelectableSubmissionFileListCategoryGridHandler;
use PKP\controllers\grid\files\SubmissionFilesCategoryGridDataProvider;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class ManageFinalDraftFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            new SubmissionFilesCategoryGridDataProvider(SubmissionFile::SUBMISSION_FILE_FINAL),
            WORKFLOW_STAGE_ID_EDITING,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT
            ],
            [
                'fetchGrid', 'fetchCategory', 'fetchRow',
                'addFile',
                'downloadFile',
                'deleteFile',
                'updateFinalDraftFiles'
            ]
        );

        // Set the grid title.
        $this->setTitle('submission.finalDraft');
    }


    //
    // Public handler methods
    //
    /**
     * Save 'manage final draft files' form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateFinalDraftFiles($args, $request)
    {
        $submission = $this->getSubmission();

        $manageFinalDraftFilesForm = new ManageFinalDraftFilesForm($submission->getId());
        $manageFinalDraftFilesForm->readInputData();

        if ($manageFinalDraftFilesForm->validate()) {
            $manageFinalDraftFilesForm->execute(
                $this->getGridCategoryDataElements($request, $this->getStageId())
            );

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }
}
