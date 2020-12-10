<?php

/**
 * @file classes/controllers/grid/GridCategoryRowCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryRowCellProvider
 * @ingroup controllers_grid
 *
 * @brief Default grid category row column's cell provider. This class will retrieve
 * the template variables from the category row instance.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class GridCategoryRowCellProvider extends GridCellProvider {

	//
	// Implemented methods from GridCellProvider.
	//
	/**
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		// Default category rows will only have the first column
		// as label columns.
		if ($column->hasFlag('firstColumn')) {
			return array('label' => $row->getCategoryLabel());
		} else {
			return array('label' => '');
		}
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_ROW_CLICK) {
		return $row->getActions($position);
	}

	/**
	 * @see GridCellProvider::render()
	 */
	function render($request, $row, $column) {
		// Default category rows will only have the first column
		// as label columns.
		if ($column->hasFlag('firstColumn')) {
			// Store the current column template.
			$template = $column->getTemplate();

			// Reset to the default column template.
			$column->setTemplate('controllers/grid/gridCell.tpl');

			// Render the cell.
			$renderedCell = parent::render($request, $row, $column);

			// Restore the original column template.
			$column->setTemplate($template);

			return $renderedCell;
		} else {
			return '';
		}
	}
}


