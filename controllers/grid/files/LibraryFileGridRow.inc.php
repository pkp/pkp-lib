<?php

/**
 * @file controllers/grid/files/LibraryFileGridRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileGridRow
 * @ingroup controllers_grid_files
 *
 * @brief Handle library file grid row requests.
 */

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class LibraryFileGridRow extends GridRow
{
    /** @var int LIBRARY_FILE_TYPE_... */
    public $_fileType;

    /** @var bool is the grid row read only */
    public $_canEdit;

    /** @var Submission the submission associated with submission library files */
    public $_submission;

    /**
     * Constructor
     *
     * @param null|mixed $submission
     */
    public function __construct($canEdit = false, $submission = null)
    {
        $this->_canEdit = $canEdit;
        $this->_submission = $submission;
        parent::__construct();
    }

    //
    // Getters / setters
    //
    /**
     * Get the file type for this row
     *
     * @return fileType
     */
    public function getFileType()
    {
        return $this->_fileType;
    }

    public function setFileType($fileType)
    {
        $this->_fileType = $fileType;
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $this->setFileType($request->getUserVar('fileType'));

        // Is this a new row or an existing row?
        $fileId = $this->getId();

        if (!empty($fileId) && $this->_canEdit) {
            // Actions
            $router = $request->getRouter();
            $actionArgs = [
                'fileId' => $fileId,
            ];

            if ($this->_submission) {
                $actionArgs['submissionId'] = $this->_submission->getId();
            }

            $this->addAction(
                new LinkAction(
                    'editFile',
                    new AjaxModal(
                        $router->url($request, null, null, 'editFile', null, $actionArgs),
                        __('grid.action.edit'),
                        'modal_edit'
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );
            $this->addAction(
                new LinkAction(
                    'deleteFile',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('common.delete'),
                        $router->url($request, null, null, 'deleteFile', null, $actionArgs),
                        'modal_delete'
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }
}
