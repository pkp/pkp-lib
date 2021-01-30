<?php

/**
 * @file controllers/grid/files/FileDateGridColumn.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 * Borrowed from FileDateGridColumn.inc.php
 *
 * @class FileDateGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a file name column.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');

class FileDateGridColumn extends GridColumn {
	/** @var boolean */
	var $_includeNotes;

	/**
	 * Constructor
	 * @param $includeNotes boolean
	 * without the history tab.
	 */
	function __construct($includeNotes = true) {
		$this->_includeNotes = $includeNotes;

		import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');
		$cellProvider = new ColumnBasedGridCellProvider();

		parent::__construct('date', 'common.date', null, null, $cellProvider,
			array('width' => 10, 'alignment' => COLUMN_ALIGNMENT_LEFT, 'anyhtml' => true));
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
		$submissionFileData = $row->getData();
		$submissionFile = $submissionFileData['submissionFile'];
		assert(is_a($submissionFile, 'SubmissionFile'));
		$mtimestamp = strtotime($submissionFile->getData('updatedAt'));
		$dateFormatLong = \Application::get()->getRequest()->getContext()->getLocalizedDateFormatLong();
		$date = strftime($dateFormatLong, $mtimestamp);
		// File age
		$age = (int)floor((date('U') - $mtimestamp) / 86400);
		switch( true ) {
			case $age <= 7:
				$cls = " pkp_helpers_text_warn"; break;
			case $age <= 28:
				$cls = " pkp_helpers_text_primary"; break;
			default:
				$cls = ""; break;
		}
		return array('label' => sprintf("<span class='label%s'>%s</span>",
										$cls, htmlspecialchars($date)));
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

