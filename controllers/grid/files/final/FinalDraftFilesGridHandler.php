<?php

/**
 * @file controllers/grid/files/final/FinalDraftFilesGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FinalDraftFilesGridHandler
 *
 * @ingroup controllers_grid_files_final
 *
 * @brief Handle the final draft files grid (displays files sent to copyediting from the review stage)
 */

namespace PKP\controllers\grid\files\final;

use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\controllers\grid\files\final\form\ManageFinalDraftFilesForm;
use PKP\core\JSONMessage;
use PKP\security\Role;

class FinalDraftFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     *  FILE_GRID_* capabilities set.
     */
    public function __construct()
    {
        parent::__construct(
            new FinalDraftFilesGridDataProvider(),
            null,
            FilesGridCapabilities::FILE_GRID_DELETE | FilesGridCapabilities::FILE_GRID_EDIT | FilesGridCapabilities::FILE_GRID_MANAGE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES
        );
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT
            ],
            [
                'fetchGrid', 'fetchRow', 'selectFiles'
            ]
        );

        $this->setTitle('submission.finalDraft');
    }

    //
    // Public handler methods
    //
    /**
     * Show the form to allow the user to select files from previous stages
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function selectFiles($args, $request)
    {
        $manageFinalDraftFilesForm = new ManageFinalDraftFilesForm($this->getSubmission()->getId());
        $manageFinalDraftFilesForm->initData();
        return new JSONMessage(true, $manageFinalDraftFilesForm->fetch($request));
    }
}
