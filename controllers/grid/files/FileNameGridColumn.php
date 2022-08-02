<?php

/**
 * @file controllers/grid/files/FileNameGridColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileNameGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a file name column.
 */

namespace PKP\controllers\grid\files;

use PKP\controllers\api\file\linkAction\DownloadFileLinkAction;
use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;

class FileNameGridColumn extends GridColumn
{
    /** @var bool */
    public $_includeNotes;

    /** @var int */
    public $_stageId;

    /** @var bool */
    public $_removeHistoryTab;

    /**
     * Constructor
     *
     * @param bool $includeNotes
     * @param int $stageId (optional)
     * @param bool $removeHistoryTab (optional) Open the information center
     * without the history tab.
     */
    public function __construct($includeNotes = true, $stageId = null, $removeHistoryTab = false)
    {
        $this->_includeNotes = $includeNotes;
        $this->_stageId = $stageId;
        $this->_removeHistoryTab = $removeHistoryTab;

        $cellProvider = new ColumnBasedGridCellProvider();

        parent::__construct(
            'name',
            'common.name',
            null,
            null,
            $cellProvider,
            ['width' => 70, 'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT, 'anyhtml' => true]
        );
    }


    //
    // Public methods
    //
    /**
     * Method expected by ColumnBasedGridCellProvider
     * to render a cell in this column.
     *
     * @copydoc ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRow($row)
    {
        $submissionFileData = $row->getData();
        $submissionFile = $submissionFileData['submissionFile'];
        assert($submissionFile instanceof \PKP\submissionFile\SubmissionFile);
        $fileExtension = pathinfo($submissionFile->getData('path'), PATHINFO_EXTENSION);
        return ['label' => '<span class="file_extension ' . $fileExtension . '">' . $submissionFile->getId() . '</span>'];
    }


    //
    // Override methods from GridColumn
    //
    /**
     * @copydoc GridColumn::getCellActions()
     */
    public function getCellActions($request, $row, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $cellActions = parent::getCellActions($request, $row, $position);

        // Retrieve the submission file.
        $submissionFileData = & $row->getData();
        assert(isset($submissionFileData['submissionFile']));
        $submissionFile = $submissionFileData['submissionFile']; /** @var SubmissionFile $submissionFile */

        // Create the cell action to download a file.
        $cellActions[] = new DownloadFileLinkAction($request, $submissionFile, $this->_getStageId());

        return $cellActions;
    }

    //
    // Private methods
    //
    /**
     * Determine whether or not submission note status should be included.
     */
    public function _getIncludeNotes()
    {
        return $this->_includeNotes;
    }

    /**
     * Get stage id, if any.
     *
     * @return mixed int or null
     */
    public function _getStageId()
    {
        return $this->_stageId;
    }
}
