<?php

/**
 * @file controllers/grid/files/copyedit/CopyeditFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CopyeditFilesGridHandler
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Handle the copyedited files grid
 */

namespace PKP\controllers\grid\files\copyedit;

use PKP\controllers\grid\files\copyedit\form\ManageCopyeditFilesForm;
use PKP\controllers\grid\files\fileList\FileListGridHandler;
use PKP\controllers\grid\files\FilesGridCapabilities;
use PKP\core\JSONMessage;
use PKP\security\Role;

class CopyeditFilesGridHandler extends FileListGridHandler
{
    /**
     * Constructor
     *  FILE_GRID_* capabilities set.
     */
    public function __construct()
    {
        parent::__construct(
            new CopyeditFilesGridDataProvider(),
            null
        );
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ],
            [
                'fetchGrid', 'fetchRow',
            ]
        );
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_ASSISTANT
            ],
            [
                'selectFiles'
            ]
        );


        $this->setTitle('submission.copyedited');
    }

    //
    // Public handler methods
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        if (0 != count(array_intersect(
            $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES),
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SUB_EDITOR]
            // Authors may also view this grid, and shouldn't be able to do anything (just view).
        ))) {
            $this->setCapabilities(new FilesGridCapabilities(FilesGridCapabilities::FILE_GRID_EDIT | FilesGridCapabilities::FILE_GRID_MANAGE | FilesGridCapabilities::FILE_GRID_VIEW_NOTES | FilesGridCapabilities::FILE_GRID_DELETE));
        }
        parent::initialize($request, $args);
    }

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
        $manageCopyeditFilesForm = new ManageCopyeditFilesForm($this->getSubmission()->getId());
        $manageCopyeditFilesForm->initData();
        return new JSONMessage(true, $manageCopyeditFilesForm->fetch($request));
    }
}
