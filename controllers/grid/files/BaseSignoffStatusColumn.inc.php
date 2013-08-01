<?php
/**
 * @file controllers/grid/files/BaseSignoffStatusColumn.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BaseSignoffStatusColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a grid column for a user group/signoff column.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');

class BaseSignoffStatusColumn extends GridColumn {
	/** @var array */
	var $_requestArgs;

	/* @var array */
	var $_userIds;

	/**
	 * Constructor
	 * @param $id string
	 * @param $title string Localization key
	 * @param $titleTranslated
	 * @param $requestArgs array
	 * @param $flags array
	 */
	function BaseSignoffStatusColumn($id = '', $title = null, $titleTranslated = null, $userIds, $requestArgs = array(), $flags = array()) {
		$this->_requestArgs = $requestArgs;
		$this->_userIds = $userIds;

		// Configure the column.
		import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');
		$cellProvider = new ColumnBasedGridCellProvider();
		parent::GridColumn(
			$id,
			$title,
			$titleTranslated,
			'controllers/grid/common/cell/statusCell.tpl',
			$cellProvider,
			$flags
		);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the cell action request parameters.
	 * @return array
	 */
	function getRequestArgs() {
		return $this->_requestArgs;
	}

	/**
	 * Get the array of user group ids relevant for this column.
	 * @return array
	 */
	function getUserIds() {
		return $this->_userIds;
	}

	//
	// Public methods
	//
	/**
	 * Method expected by ColumnBasedGridCellProvider
	 * to render a cell in this column.
	 * @copydoc ColumnBasedGridCellProvider::getTemplateVarsFromRow()
	 * @param $row GridRow
	 */
	function getTemplateVarsFromRow($row) {
		return array('status' => $this->_getSignoffStatus($row));
	}


	//
	// Overridden methods from GridColumn
	//
	/**
	 * @copydoc GridColumn::getCellActions()
	 */
	function getCellActions($request, $row) {
		return array();
	}


	//
	// Private helper methods
	//
	/**
	 * Identify the signoff status of a row.
	 * @param $row GridRow
	 * @return string
	 */
	function _getSignoffStatus($row) {
		assert(false); // Abstract method
	}
}

?>
