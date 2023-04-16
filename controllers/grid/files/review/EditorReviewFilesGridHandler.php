<?php

/**
 * @file controllers/grid/files/review/EditorReviewFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorReviewFilesGridHandler
 *
 * @ingroup controllers_grid_files_review
 *
 * @brief Handle the editor review file grid (displays files that are to be reviewed in the current round)
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\review\form\ManageReviewFilesForm;
use PKP\core\JSONMessage;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class EditorReviewFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE;
        parent::__construct(
            new ReviewGridDataProvider($fileStage),
            null,
            FilesGridCapabilities::FILE_GRID_EDIT | FilesGridCapabilities::FILE_GRID_MANAGE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_DELETE
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow', 'selectFiles']
        );

        $this->setTitle('reviewer.submission.reviewFiles');
    }


    //
    // Public handler methods
    //
    /**
     * Show the form to allow the user to select review files
     * (bring in/take out files from submission stage to review stage)
     *
     * FIXME: Move to its own handler so that it can be re-used among grids.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function selectFiles($args, $request)
    {
        $submission = $this->getSubmission();

        $manageReviewFilesForm = new ManageReviewFilesForm($submission->getId(), $this->getRequestArg('stageId'), $this->getRequestArg('reviewRoundId'));

        $manageReviewFilesForm->initData();
        return new JSONMessage(true, $manageReviewFilesForm->fetch($request));
    }
}
