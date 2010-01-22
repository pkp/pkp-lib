<?php

/**
 * @file classes/controllers/grid/column/GridCellProvider.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCellProvider
 * @ingroup controllers_grid_column
 *
 * @brief Base class for a grid column's cell provider
 */


class GridCellProvider {
	/**
	 * Constructor
	 */
	function GridCellProvider() {
	}

	/**
	 * To be used by a GridRowHandler to generate a rendered representation of
	 * the element for the given column.
	 *
	 * Subclasses will implement this to transform a data element
	 * into a cell that can be rendered by the cell template. The default
	 * implementation assumes a simple labeled cell and a data element array
	 * that has column ids as keys. It uses the default template and actions
	 * configured for the given column.
	 *
	 * @param $row GridRowHandler
	 * @param $column GridColumn
	 * @return string the rendered representation of the element for the given column
	 */
	function render(&$row, &$column) {
		$columnId = $column->getId();
		assert(!empty($columnId));

		// Assume an array element by default
		$element =& $row->getData();
		if(isset($element[$columnId])) {
			$label = $element[$columnId];
		} else {
			$label = '';
		}

		// Construct a default cell id
		$rowId = $row->getId();
		assert(!empty($rowId));
		$cellId = $rowId.'-'.$columnId;

		// Pass control to the view to render the cell
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('id', $cellId);
		$templateMgr->assign('label', $label);
		$templateMgr->assign_by_ref('column', $column);
		$templateMgr->assign_by_ref('actions', $column->getActions());

		$template = $column->getTemplate();
		assert(!empty($template));
		return $templateMgr->fetch($template);
	}
}