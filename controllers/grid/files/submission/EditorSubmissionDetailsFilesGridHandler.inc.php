<?php

/**
 * @file controllers/grid/files/submission/EditorSubmissionDetailsFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorSubmissionDetailsFilesGridHandler
 * @ingroup controllers_grid_files_submission
 *
 * @brief Handle submission file grid requests on the editor's submission details pages.
 */

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\submission\SubmissionFile;

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class EditorSubmissionDetailsFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');
        $dataProvider = new SubmissionFilesGridDataProvider(SubmissionFile::SUBMISSION_FILE_SUBMISSION);
        parent::__construct(
            $dataProvider,
            WORKFLOW_STAGE_ID_SUBMISSION,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_DOWNLOAD_ALL | FilesGridCapabilities::FILE_GRID_EDIT
        );

        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow']
        );

        // Grid title.
        $this->setTitle('submission.submit.submissionFiles');
    }
}
