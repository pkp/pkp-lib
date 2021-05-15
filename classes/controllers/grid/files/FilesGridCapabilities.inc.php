<?php

/**
 * @file classes/controllers/grid/files/FilesGridCapabilities.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilesGridCapabilities
 * @ingroup classes_controllers_grid_files
 *
 * @brief Defines files grid capabilities. Should be used by grid handlers
 * that handle submission files to store which capabilities the grid has.
 */

namespace PKP\controllers\grid\files;

use DownloadAllLinkAction;

// FIXME: Namespacing
import('lib.pkp.controllers.grid.files.fileList.linkAction.DownloadAllLinkAction');
use PKP\file\FileArchive;

class FilesGridCapabilities
{
    // Define the grid capabilities.
    public const FILE_GRID_ADD = 0x00000001;
    public const FILE_GRID_DOWNLOAD_ALL = 0x00000002;
    public const FILE_GRID_DELETE = 0x00000004;
    public const FILE_GRID_VIEW_NOTES = 0x00000008;
    public const FILE_GRID_MANAGE = 0x00000010;
    public const FILE_GRID_EDIT = 0x00000020;

    /** @var boolean */
    public $_canAdd;

    /** @var boolean */
    public $_canViewNotes;

    /** @var boolean */
    public $_canDownloadAll;

    /** @var boolean */
    public $_canDelete;

    /** @var boolean */
    public $_canManage;

    /** @var boolean */
    public $_canEdit;

    /**
     * Constructor
     *
     * @param $capabilities integer A bit map with zero or more
     *  FILE_GRID_* capabilities set.
     */
    public function __construct($capabilities = 0)
    {
        $this->setCanAdd($capabilities & self::FILE_GRID_ADD);
        $this->setCanDownloadAll($capabilities & self::FILE_GRID_DOWNLOAD_ALL);
        $this->setCanDelete($capabilities & self::FILE_GRID_DELETE);
        $this->setCanViewNotes($capabilities & self::FILE_GRID_VIEW_NOTES);
        $this->setCanManage($capabilities & self::FILE_GRID_MANAGE);
        $this->setCanEdit($capabilities & self::FILE_GRID_EDIT);
    }


    //
    // Getters and Setters
    //
    /**
     * Does this grid allow the addition of files or revisions?
     *
     * @return boolean
     */
    public function canAdd()
    {
        return $this->_canAdd;
    }

    /**
     * Set whether or not the grid allows the addition of files or revisions.
     *
     * @param $canAdd boolean
     */
    public function setCanAdd($canAdd)
    {
        $this->_canAdd = (bool) $canAdd;
    }

    /**
     * Does this grid allow viewing of notes?
     *
     * @return boolean
     */
    public function canViewNotes()
    {
        return $this->_canViewNotes;
    }

    /**
     * Set whether this grid allows viewing of notes or not.
     *
     * @return boolean
     */
    public function setCanViewNotes($canViewNotes)
    {
        $this->_canViewNotes = $canViewNotes;
    }

    /**
     * Can the user download all files as an archive?
     *
     * @return boolean
     */
    public function canDownloadAll()
    {
        return $this->_canDownloadAll && FileArchive::isFunctional();
    }

    /**
     * Set whether user can download all files as an archive or not.
     *
     * @return boolean
     */
    public function setCanDownloadAll($canDownloadAll)
    {
        $this->_canDownloadAll = $canDownloadAll;
    }

    /**
     * Can the user delete files from this grid?
     *
     * @return boolean
     */
    public function canDelete()
    {
        return $this->_canDelete;
    }

    /**
     * Set whether or not the user can delete files from this grid.
     *
     * @param $canDelete boolean
     */
    public function setCanDelete($canDelete)
    {
        $this->_canDelete = (bool) $canDelete;
    }

    /**
     * Whether the grid allows file management (select existing files to add to grid)
     *
     * @return boolean
     */
    public function canManage()
    {
        return $this->_canManage;
    }

    /**
     * Set whether the grid allows file management (select existing files to add to grid)
     *
     * @return boolean
     */
    public function setCanManage($canManage)
    {
        $this->_canManage = $canManage;
    }

    /**
     * Whether the grid allows file metadata editing
     *
     * @return boolean
     */
    public function canEdit()
    {
        return $this->_canEdit;
    }

    /**
     * Set whether the grid allows file metadata editing
     *
     * @return boolean
     */
    public function setCanEdit($canEdit)
    {
        $this->_canEdit = $canEdit;
    }

    /**
     * Get the download all link action.
     *
     * @param $request PKPRequest
     * @param $files array The files to be downloaded.
     * @param $linkParams array The link action request
     * parameters.
     *
     * @return LinkAction
     */
    public function getDownloadAllAction($request, $files, $linkParams)
    {
        if (sizeof($files) > 0) {
            return new DownloadAllLinkAction($request, $linkParams, $files);
        } else {
            return null;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\files\FilesGridCapabilities', '\FilesGridCapabilities');
    foreach ([
        'FILE_GRID_ADD',
        'FILE_GRID_DOWNLOAD_ALL',
        'FILE_GRID_DELETE',
        'FILE_GRID_VIEW_NOTES',
        'FILE_GRID_MANAGE',
        'FILE_GRID_EDIT',
    ] as $constantName) {
        define($constantName, constant('\FilesGridCapabilities::' . $constantName));
    }
}
