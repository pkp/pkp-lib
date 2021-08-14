<?php

/**
 * @file controllers/grid/files/submissionDocuments/SubmissionDocumentsFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileGridHandler
 * @ingroup controllers_grid_files_submissionDocuments
 *
 * @brief Handle submission documents file grid requests.
 */

import('lib.pkp.controllers.grid.files.LibraryFileGridHandler');
import('lib.pkp.controllers.grid.files.submissionDocuments.SubmissionDocumentsFilesGridDataProvider');

use APP\template\TemplateManager;
use PKP\linkAction\LinkAction;

use PKP\security\Role;

class SubmissionDocumentsFilesGridHandler extends LibraryFileGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(new SubmissionDocumentsFilesGridDataProvider());
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            [
                'addFile', 'uploadFile', 'saveFile', // Adding new library files
                'editFile', 'updateFile', // Editing existing library files
                'deleteFile', 'viewLibrary'
            ]
        );
    }


    //
    // Overridden template methods
    //
    /**
     * Configure the grid
     *
     * @see LibraryFileGridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        $this->setCanEdit(true); // this grid can always be edited.
        parent::initialize($request, $args);

        $this->setTitle(null);

        $router = $request->getRouter();

        // Add grid-level actions

        if ($this->canEdit()) {
            $this->addAction(
                new LinkAction(
                    'addFile',
                    new AjaxModal(
                        $router->url($request, null, null, 'addFile', null, $this->getActionArgs()),
                        __('grid.action.addFile'),
                        'modal_add_file'
                    ),
                    __('grid.action.addFile'),
                    'add'
                )
            );
        }

        $this->addAction(
            new LinkAction(
                'viewLibrary',
                new AjaxModal(
                    $router->url($request, null, null, 'viewLibrary', null, $this->getActionArgs()),
                    __('grid.action.viewLibrary'),
                    'modal_information'
                ),
                __('grid.action.viewLibrary'),
                'more_info'
            )
        );
    }

    /**
     * Retrieve the arguments for the 'add file' action.
     *
     * @return array
     */
    public function getActionArgs()
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $actionArgs = [
            'submissionId' => $submission->getId(),
        ];

        return $actionArgs;
    }

    /**
     * Get the row handler - override the default row handler
     *
     * @return LibraryFileGridRow
     */
    protected function getRowInstance()
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        return new LibraryFileGridRow($this->canEdit(), $submission);
    }

    //
    // Public File Grid Actions
    //

    /**
     * Load the (read only) context file library.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewLibrary($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('isModal', true);
        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        $templateMgr->assign('canEdit', !empty(array_intersect([Role::ROLE_ID_MANAGER], $userRoles)));
        return $templateMgr->fetchJson('controllers/modals/documentLibrary/publisherLibrary.tpl');
    }

    /**
     * Returns a specific instance of the new form for this grid.
     *
     * @param Context $context
     *
     * @return NewLibraryFileForm
     */
    public function _getNewFileForm($context)
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        import('lib.pkp.controllers.grid.files.submissionDocuments.form.NewLibraryFileForm');
        return new NewLibraryFileForm($context->getId(), $submission->getId());
    }

    /**
     * Returns a specific instance of the edit form for this grid.
     *
     * @param Context $context
     * @param int $fileId
     *
     * @return EditLibraryFileForm
     */
    public function _getEditFileForm($context, $fileId)
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        import('lib.pkp.controllers.grid.files.submissionDocuments.form.EditLibraryFileForm');
        return new EditLibraryFileForm($context->getId(), $fileId, $submission->getId());
    }
}
