<?php

/**
 * @file controllers/grid/files/review/ReviewerReviewFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewFilesGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Handle the reviewer review file grid (for reviewers to download files to review)
 */

namespace PKP\controllers\grid\files\review;

use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\security\Role;

class ReviewerReviewFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewerReviewFilesGridDataProvider(),
            null
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid title.
        $this->setTitle('reviewer.submission.reviewFiles');
    }
}
