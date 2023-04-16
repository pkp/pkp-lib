<?php

/**
 * @file controllers/grid/files/review/WorkflowReviewRevisionsGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowReviewRevisionsGridHandler
 *
 * @ingroup controllers_grid_files_review
 *
 * @brief Display in workflow pages the file revisions that authors have uploaded.
 */

namespace PKP\controllers\grid\files\review;

use APP\core\Application;
use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class WorkflowReviewRevisionsGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $stageId = (int) Application::get()->getRequest()->getUserVar('stageId');
        $fileStage = $stageId === WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION : SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION;
        parent::__construct(
            new ReviewGridDataProvider($fileStage),
            null,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_EDIT | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_DELETE
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow', 'addFile']
        );

        $this->setTitle('editor.submission.revisions');
    }
}
