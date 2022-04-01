<?php

/**
 * @file controllers/grid/files/LibraryFileGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Base class for handling library file grid requests.
 */

import('lib.pkp.controllers.grid.files.LibraryFileGridRow');
import('lib.pkp.controllers.grid.files.LibraryFileGridCategoryRow');

use APP\file\LibraryFileManager;
use PKP\controllers\grid\CategoryGridHandler;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

use PKP\security\Role;

class LibraryFileGridHandler extends CategoryGridHandler
{
    /** @var Context The context for this grid */
    public $_context;

    /** @var bool Whether or not the grid is editable */
    public $_canEdit;

    /**
     * Constructor
     */
    public function __construct($dataProvider)
    {
        parent::__construct($dataProvider);
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            [
                'fetchGrid', 'fetchCategory', 'fetchRow', // Parent grid-level actions
            ]
        );
    }


    //
    // Getters/Setters
    //
    /**
     * Get the context
     *
     * @return object context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Can the user edit/add files in this grid?
     *
     * @return bool
     */
    public function canEdit()
    {
        return $this->_canEdit;
    }

    /**
     * Set whether or not the user can edit or add files.
     *
     * @param bool $canEdit
     */
    public function setCanEdit($canEdit)
    {
        $this->_canEdit = $canEdit;
    }

    //
    // Overridden template methods
    //

    /**
     * Configure the grid
     *
     * @see CategoryGridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $router = $request->getRouter();
        $this->_context = $router->getContext($request);

        // Set name
        $this->setTitle('manager.publication.library');

        // Columns
        // Basic grid row configuration
        $this->addColumn($this->getFileNameColumn());

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
    }

    //
    // Implement template methods from CategoryGridHandler
    //
    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    protected function getCategoryRowInstance()
    {
        return new LibraryFileGridCategoryRow($this->getContext());
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $context = $this->getContext();
        $libraryFileManager = new LibraryFileManager($context->getId());
        $fileTypeKeys = $libraryFileManager->getTypeSuffixMap();
        foreach (array_keys($fileTypeKeys) as $key) {
            $data[$key] = $key;
        }
        return $data;
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * Get the row handler - override the default row handler
     *
     * @return LibraryFileGridRow
     */
    protected function getRowInstance()
    {
        return new LibraryFileGridRow($this->canEdit());
    }

    /**
     * Get an instance of the cell provider for this grid.
     *
     * @return LibraryFileGridCellProvider
     */
    public function getFileNameColumn()
    {
        import('lib.pkp.controllers.grid.files.LibraryFileGridCellProvider');
        return new GridColumn(
            'files',
            'grid.libraryFiles.column.files',
            null,
            null,
            new LibraryFileGridCellProvider()
        );
    }

    //
    // Public File Grid Actions
    //
    /**
     * An action to add a new file
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function addFile($args, $request)
    {
        $this->initialize($request);
        $router = $request->getRouter();
        $context = $request->getContext();

        $fileForm = $this->_getNewFileForm($context);
        $fileForm->initData();

        return new JSONMessage(true, $fileForm->fetch($request));
    }

    /**
     * Save a new library file.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveFile($args, $request)
    {
        $router = $request->getRouter();
        $context = $request->getContext();

        $fileForm = $this->_getNewFileForm($context);
        $fileForm->readInputData();

        if ($fileForm->validate()) {
            $fileId = $fileForm->execute();

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }

    /**
     * An action to add a new file
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editFile($args, $request)
    {
        $this->initialize($request);
        assert(isset($args['fileId']));
        $fileId = (int) $args['fileId'];

        $router = $request->getRouter();
        $context = $request->getContext();

        $fileForm = $this->_getEditFileForm($context, $fileId);
        $fileForm->initData();

        return new JSONMessage(true, $fileForm->fetch($request));
    }

    /**
     * Save changes to an existing library file.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateFile($args, $request)
    {
        assert(isset($args['fileId']));
        $fileId = (int) $args['fileId'];

        $router = $request->getRouter();
        $context = $request->getContext();

        $fileForm = $this->_getEditFileForm($context, $fileId);
        $fileForm->readInputData();

        if ($fileForm->validate()) {
            $fileForm->execute();

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }

    /**
     * Delete a file
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteFile($args, $request)
    {
        $fileId = $args['fileId'] ?? null;
        $router = $request->getRouter();
        $context = $router->getContext($request);

        if ($request->checkCSRF() && $fileId) {
            $libraryFileManager = new LibraryFileManager($context->getId());
            $libraryFileManager->deleteById($fileId);

            return \PKP\db\DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }

    /**
     * Upload a new library file.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function uploadFile($args, $request)
    {
        $router = $request->getRouter();
        $context = $request->getContext();
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
        if ($temporaryFile) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes([
                'temporaryFileId' => $temporaryFile->getId()
            ]);
            return $json;
        } else {
            return new JSONMessage(false, __('common.uploadFailed'));
        }
    }

    /**
     * Returns a specific instance of the new form for this grid.
     *  Must be implemented by subclasses.
     *
     * @param Context $context
     */
    public function _getNewFileForm($context)
    {
        assert(false);
    }

    /**
     * Returns a specific instance of the edit form for this grid.
     *  Must be implemented by subclasses.
     *
     * @param Press $context
     * @param int $fileId
     */
    public function _getEditFileForm($context, $fileId)
    {
        assert(false);
    }

    /**
     * Retrieve the arguments for the 'add file' action.
     *
     * @return array
     */
    public function getActionArgs()
    {
        return [];
    }
}
