<?php

/**
 * @file controllers/grid/files/review/SelectableReviewRevisionsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableReviewRevisionsGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display the file revisions authors have uploaded in a selectable grid.
 *   Used for selecting files to send to external review or editorial stages.
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');
import('lib.pkp.controllers.grid.files.review.ReviewRevisionsGridDataProvider');

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\security\Role;

class SelectableReviewRevisionsGridHandler extends SelectableFileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new ReviewRevisionsGridDataProvider(),
            null,
            FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid information.
        $this->setTitle('editor.submission.revisions');
    }

    //
    // Implemented methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        return true;
    }
}
