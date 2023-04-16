<?php

/**
 * @file controllers/grid/files/FilesGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilesGridDataProvider
 *
 * @ingroup controllers_grid_files
 *
 * @brief Basic files grid data provider.
 */

namespace PKP\controllers\grid\files;

use APP\core\Application;
use PKP\controllers\grid\GridDataProvider;

class FilesGridDataProvider extends GridDataProvider
{
    /** @var int */
    public $_uploaderRoles;

    /** @var bool */
    public $_viewableOnly = false;


    //
    // Getters and Setters
    //
    /**
     * Set the uploader roles.
     *
     * @param array $roleAssignments The grid's
     *  role assignment from which the uploader roles
     *  will be extracted.
     */
    public function setUploaderRoles($roleAssignments)
    {
        $this->_uploaderRoles = array_keys($roleAssignments);
    }

    /**
     * Get the uploader roles.
     *
     * @return array
     */
    public function getUploaderRoles()
    {
        assert(is_array($this->_uploaderRoles) && !empty($this->_uploaderRoles));
        return $this->_uploaderRoles;
    }

    /**
     * Load only viewable files flag.
     *
     * @param bool $viewableOnly
     */
    public function setViewableOnly($viewableOnly)
    {
        $this->_viewableOnly = $viewableOnly;
    }


    //
    // Public helper methods
    //
    /**
     * Configures and returns the action to add a file.
     *
     * NB: Must be overridden by subclasses (if implemented).
     *
     * @param Request $request
     *
     * @return AddFileLinkAction
     */
    public function getAddFileAction($request)
    {
        assert(false);
    }

    /**
     * Configures and returns the select files action.
     *
     * NB: Must be overridden by subclasses (if implemented).
     *
     * @param Request $request
     *
     * @return SelectFilesLinkAction
     */
    public function getSelectAction($request)
    {
        assert(false);
    }


    //
    // Protected helper methods
    //
    /**
     * Get the authorized submission.
     *
     * @return Submission
     */
    protected function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }
}
