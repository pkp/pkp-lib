<?php
/**
 * @filecontrollers/grid/files/attachment/ReviewerReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Handle file grid requests.
 */

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');
import('lib.pkp.controllers.grid.files.attachment.ReviewerReviewAttachmentGridDataProvider');

class ReviewerReviewAttachmentsGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewerReviewAttachmentGridDataProvider(SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT),
            null,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_REVIEWER],
            [
                'fetchGrid', 'fetchRow'
            ]
        );

        // Set the grid title.
        $this->setTitle('reviewer.submission.reviewerFiles');
    }

    /**
     * @copydoc FileListGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        // Watch for flag from including template to warn about the
        // review already being complete. If so, remove some capabilities.
        $capabilities = $this->getCapabilities();
        if ($request->getUserVar('reviewIsClosed')) {
            $capabilities->setCanAdd(false);
            $capabilities->setCanDelete(false);
        }

        parent::initialize($request, $args);
    }
}
