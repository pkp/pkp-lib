<?php

/**
 * @file controllers/grid/files/attachment/AuthorReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Handle review attachment grid requests (author's perspective)
 */

namespace PKP\controllers\grid\files\attachment;

use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\review\ReviewGridDataProvider;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class AuthorReviewAttachmentsGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewGridDataProvider(SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, true),
            null
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid title.
        $this->setTitle('grid.reviewAttachments.title');
    }
}
