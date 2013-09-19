<?php

/**
 * @file controllers/grid/files/FileNameGridColumn.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileNameGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a file name column.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');

class FileNameGridColumn extends GridColumn {
	/** @var boolean */
	var $_includeNotes;

	/** @var int */
	var $_stageId;

	/** @var boolean */
	var $_removeHistoryTab;

	/**
	 * Constructor
	 * @param $includeNotes boolean
	 * @param $stageId int (optional)
	 * @param $removeHistoryTab boolean (optional) Open the information center
	 * without the history tab.
	 */
	function FileNameGridColumn($includeNotes = true, $stageId = null, $removeHistoryTab = false) {
		$this->_includeNotes = $includeNotes;
		$this->_stageId = $stageId;
		$this->_removeHistoryTab = $removeHistoryTab;

		import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');
		$cellProvider = new ColumnBasedGridCellProvider();

		parent::GridColumn('name', 'common.name', null, 'controllers/grid/gridCell.tpl', $cellProvider,
			array('width' => 60, 'alignment' => COLUMN_ALIGNMENT_LEFT));
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
	function getTemplateVarsFromRow($row) {
		// We do not need any template variables because
		// the only content of this column's cell will be
		// an action. See FileNameGridColumn::getCellActions().
		return array('label' => '');
	}


	//
	// Override methods from GridColumn
	//
	/**
	 * @copydoc GridColumn::getCellActions()
	 */
	function getCellActions($request, $row, $position = GRID_ACTION_POSITION_DEFAULT) {
		$cellActions = parent::getCellActions($request, $row, $position);

		// Retrieve the submission file.
		$submissionFileData =& $row->getData();
		assert(isset($submissionFileData['submissionFile']));
		$submissionFile = $submissionFileData['submissionFile']; /* @var $submissionFile SubmissionFile */

		// Create the cell action to download a file.
		import('lib.pkp.controllers.api.file.linkAction.DownloadFileLinkAction');
		$cellActions[] = new DownloadFileLinkAction($request, $submissionFile, $this->_getStageId());

		if ($this->_getIncludeNotes()) {
			import('lib.pkp.controllers.informationCenter.linkAction.FileNotesLinkAction');
			$user = $request->getUser();
			$cellActions[] = new FileNotesLinkAction($request, $submissionFile, $user, $this->_getStageId(), $this->_removeHistoryTab);
		}
		return $cellActions;
	}

	//
	// Private methods
	//
	/**
	 * Determine whether or not submission note status should be included.
	 */
	function _getIncludeNotes() {
		return $this->_includeNotes;
	}

	/**
	 * Get stage id, if any.
	 * @return mixed int or null
	 */
	function _getStageId() {
		return $this->_stageId;
	}
}

?>
