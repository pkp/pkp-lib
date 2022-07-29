<?php

/**
 * @file controllers/grid/files/attachment/AuthorOpenReviewAttachmentsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorOpenReviewAttachmentsGridHandler
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Handle review attachment grid requests in open reviews (author's perspective)
 */

namespace PKP\controllers\grid\files\attachment;

use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class AuthorOpenReviewAttachmentsGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        // Show also files that are not viewable by default
        parent::__construct(
            new ReviewerReviewAttachmentGridDataProvider(SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT, false),
            null
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow']
        );
    }
}
