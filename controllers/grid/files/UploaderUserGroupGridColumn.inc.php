<?php

/**
 * @file controllers/grid/files/UploaderUserGroupGridColumn.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UploaderUserGroupGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a column to show a folder icon when user group uploaded the current file.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');
import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');

class UploaderUserGroupGridColumn extends GridColumn {
	/* @var UserGroup */
	var $_userGroup;

	/**
	 * Constructor
	 */
	function UploaderUserGroupGridColumn($userGroup, $flags = array()) {
		$this->_userGroup = $userGroup;
		$cellProvider = new ColumnBasedGridCellProvider();
		parent::GridColumn(
			'userGroup-' . $userGroup->getId(),
			null, $userGroup->getLocalizedName(),
			'controllers/grid/common/cell/statusCell.tpl',
			$cellProvider, $flags
		);
	}

	//
	// Getter
	//
	function getUserGroup() {
		return $this->_userGroup;
	}

	//
	// Public methods
	//
	/**
	 * Method expected by ColumnBasedGridCellProvider
	 * to render a cell in this column.
	 *
	 * @see ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRow($row) {
		$rowData = $row->getData();
		$userGroup = $this->getUserGroup();
		$submissionFile = $rowData['submissionFile'];
		if ($submissionFile->getUserGroupId() == $userGroup->getId()) {
			return array('status' => 'uploaded');
		}
		return array('status' => '');
	}
}

?>
