<?php

/**
 * @file controllers/grid/files/productionReady/ProductionReadyFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProductionReadyFilesGridHandler
 * @ingroup controllers_grid_files_productionready
 *
 * @brief Handle the fair copy files grid (displays copyedited files ready to move to proofreading)
 */

use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\submission\SubmissionFile;

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class ProductionReadyFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');
        parent::__construct(
            new SubmissionFilesGridDataProvider(SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY),
            WORKFLOW_STAGE_ID_PRODUCTION,
            FilesGridCapabilities::FILE_GRID_ADD | FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_EDIT | FilesGridCapabilities::FILE_GRID_DOWNLOAD_ALL
        );

        $this->addRoleAssignment(
            [
                ROLE_ID_SUB_EDITOR,
                ROLE_ID_MANAGER,
                ROLE_ID_ASSISTANT
            ],
            [
                'fetchGrid', 'fetchRow',
                'addFile',
                'downloadFile',
                'deleteFile',
            ]
        );

        $this->setTitle('editor.submission.production.productionReadyFiles');
    }
}
