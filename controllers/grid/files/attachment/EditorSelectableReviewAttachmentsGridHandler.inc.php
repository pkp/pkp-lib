<?php
/**
 * @file controllers/grid/files/attachment/EditorSelectableReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorSelectableReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachments
 *
 * @brief Selectable review attachment grid requests (editor's perspective).
 */

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class EditorSelectableReviewAttachmentsGridHandler extends SelectableFileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            // This grid lists all review round files, but creates attachments
            new ReviewGridDataProvider(SubmissionFile::SUBMISSION_FILE_ATTACHMENT, false, true),
            null,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid title.
        $this->setTitle('grid.reviewAttachments.send.title');
    }

    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        $file = $gridDataElement['submissionFile'];
        switch ($file->getFileStage()) {
            case SubmissionFile::SUBMISSION_FILE_ATTACHMENT: return true;
            case SubmissionFile::SUBMISSION_FILE_REVIEW_FILE: return false;
        }
        return $file->getViewable();
    }

    /**
     * @copydoc SelectableFileListGridHandler::getSelectName()
     */
    public function getSelectName()
    {
        return 'selectedAttachments';
    }
}
