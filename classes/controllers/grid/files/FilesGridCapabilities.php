<?php

/**
 * @file classes/controllers/grid/files/FilesGridCapabilities.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilesGridCapabilities
 *
 * @ingroup classes_controllers_grid_files
 *
 * @brief Defines files grid capabilities. Should be used by grid handlers
 * that handle submission files to store which capabilities the grid has.
 */

namespace PKP\controllers\grid\files;

use PKP\controllers\grid\files\fileList\linkAction\DownloadAllLinkAction;
use PKP\core\PKPRequest;
use PKP\file\FileArchive;
use PKP\linkAction\LinkAction;

class FilesGridCapabilities
{
    // Define the grid capabilities.
    public const FILE_GRID_ADD = 0x00000001;
    public const FILE_GRID_DOWNLOAD_ALL = 0x00000002;
    public const FILE_GRID_DELETE = 0x00000004;
    public const FILE_GRID_VIEW_NOTES = 0x00000008;
    public const FILE_GRID_MANAGE = 0x00000010;
    public const FILE_GRID_EDIT = 0x00000020;

    /** @var bool */
    public $_canAdd;

    /** @var bool */
    public $_canViewNotes;

    /** @var bool */
    public $_canDownloadAll;

    /** @var bool */
    public $_canDelete;

    /** @var bool */
    public $_canManage;

    /** @var bool */
    public $_canEdit;

    /**
     * Constructor
     *
     * @param int $capabilities A bit map with zero or more
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
     * @return bool
     */
    public function canAdd()
    {
        return $this->_canAdd;
    }

    /**
     * Set whether or not the grid allows the addition of files or revisions.
     *
     * @param bool $canAdd
     */
    public function setCanAdd($canAdd)
    {
        $this->_canAdd = (bool) $canAdd;
    }

    /**
     * Does this grid allow viewing of notes?
     *
     * @return bool
     */
    public function canViewNotes()
    {
        return $this->_canViewNotes;
    }

    /**
     * Set whether this grid allows viewing of notes or not.
     */
    public function setCanViewNotes($canViewNotes)
    {
        $this->_canViewNotes = $canViewNotes;
    }

    /**
     * Can the user download all files as an archive?
     *
     * @return bool
     */
    public function canDownloadAll()
    {
        return $this->_canDownloadAll && FileArchive::isFunctional();
    }

    /**
     * Set whether user can download all files as an archive or not.
     */
    public function setCanDownloadAll($canDownloadAll)
    {
        $this->_canDownloadAll = $canDownloadAll;
    }

    /**
     * Can the user delete files from this grid?
     *
     * @return bool
     */
    public function canDelete()
    {
        return $this->_canDelete;
    }

    /**
     * Set whether or not the user can delete files from this grid.
     *
     * @param bool $canDelete
     */
    public function setCanDelete($canDelete)
    {
        $this->_canDelete = (bool) $canDelete;
    }

    /**
     * Whether the grid allows file management (select existing files to add to grid)
     *
     * @return bool
     */
    public function canManage()
    {
        return $this->_canManage;
    }

    /**
     * Set whether the grid allows file management (select existing files to add to grid)
     */
    public function setCanManage($canManage)
    {
        $this->_canManage = $canManage;
    }

    /**
     * Whether the grid allows file metadata editing
     *
     * @return bool
     */
    public function canEdit()
    {
        return $this->_canEdit;
    }

    /**
     * Set whether the grid allows file metadata editing
     */
    public function setCanEdit($canEdit)
    {
        $this->_canEdit = $canEdit;
    }

    /**
     * Get the download all link action.
     *
     * @param PKPRequest $request
     * @param array $files The files to be downloaded.
     * @param array $linkParams The link action request
     * parameters.
     *
     * @return ?LinkAction
     */
    public function getDownloadAllAction($request, $files, $linkParams)
    {
        if (sizeof($files) > 0) {
            return new DownloadAllLinkAction($request, $linkParams);
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
