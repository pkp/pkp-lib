<?php

/**
 * @file controllers/grid/files/FilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilesGridDataProvider
 * @ingroup controllers_grid_files
 *
 * @brief Basic files grid data provider.
 */

use PKP\controllers\grid\GridDataProvider;

class FilesGridDataProvider extends GridDataProvider
{
    /** @var integer */
    public $_uploaderRoles;

    /** @var boolean */
    public $_viewableOnly = false;


    //
    // Getters and Setters
    //
    /**
     * Set the uploader roles.
     *
     * @param $roleAssignments array The grid's
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
     * @param $viewableOnly boolean
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
     * @param $request Request
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
     * @param $request Request
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
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }
}
