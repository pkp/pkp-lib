<?php

/**
 * @file classes/controllers/grid/GridCellProvider.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Base class for a grid column's cell provider
 */


class GridCellProvider {
	/**
	 * Constructor
	 */
	function GridCellProvider() {
	}

	//
	// Public methods
	//
	/**
	 * To be used by a GridRow to generate a rendered representation of
	 * the element for the given column.
	 *
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return string the rendered representation of the element for the given column
	 */
	function render(&$row, &$column) {
		$columnId = $column->getId();
		assert(!empty($columnId));

		$element =& $row->getData();
		$templateVars = $this->getTemplateVarsFromElement($element, $columnId);
		// Construct a default cell id
		$rowId = $row->getId();

		assert(isset($rowId));
		$cellId = $rowId.'-'.$columnId;

		// Pass control to the view to render the cell
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('id', $cellId);
		$templateMgr->assign_by_ref('column', $column);
		$templateMgr->assign_by_ref('actions', $column->getActions());
		$templateMgr->assign_by_ref('flags', $column->getFlags());

		// assign all values from element (by ref, just in case they are objects)
		foreach ($templateVars as $varName => $varValue) {
			$templateMgr->assign($varName, $varValue);
		}
		$template = $column->getTemplate();
		assert(!empty($template));
		return $templateMgr->fetch($template);
	}

	//
	// Protected template methods
	//
	/**
	 * Subclasses have to implement this method to extract variables
	 * for a given column from a data element so that they may be assigned
	 * to template before rendering.
	 * @param $element mixed
	 * @param $columnId string
	 * @return array()
	 */
	function getTemplateVarsFromElement(&$element, $columnId) {
		return array();
	}
}