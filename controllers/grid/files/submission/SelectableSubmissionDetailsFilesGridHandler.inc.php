<?php

/**
 * @file controllers/grid/files/submission/SelectableSubmissionDetailsFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableSubmissionDetailsFilesGridHandler
 * @ingroup controllers_grid_files_submission
 *
 * @brief Handle submission file grid requests in the editor's 'promote submission' modal.
 */

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\submission\SubmissionFile;

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

class SelectableSubmissionDetailsFilesGridHandler extends SelectableFileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');
        // Pass in null stageId to be set in initialize from request var.
        parent::__construct(
            new SubmissionFilesGridDataProvider(SubmissionFile::SUBMISSION_FILE_SUBMISSION),
            null,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_VIEW_NOTES
        );

        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid title.
        $this->setTitle('submission.submit.submissionFiles');
    }
}
