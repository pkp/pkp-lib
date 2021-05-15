<?php

/**
 * @file controllers/grid/files/final/SelectableFinalDraftFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableFinalDraftFilesGridHandler
 * @ingroup controllers_grid_files_final
 *
 * @brief Handle copyedited files grid requests to promote to production stage.
 */

import('lib.pkp.controllers.grid.files.fileList.SelectableFileListGridHandler');

use PKP\controllers\grid\files\FilesGridCapabilities;

class SelectableFinalDraftFilesGridHandler extends SelectableFileListGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.final.FinalDraftFilesGridDataProvider');
        parent::__construct(
            new FinalDraftFilesGridDataProvider(),
            WORKFLOW_STAGE_ID_EDITING,
            FilesGridCapabilities::FILE_GRID_VIEW_NOTES
        );

        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow']
        );

        // Set the grid title.
        $this->setTitle('submission.finalDraft');
    }

    //
    // Implemented methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        return false;
    }
}
