<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsCellProvider.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridCellProvider
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Cell provider for title column of a NavigationMenuItems grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class NavigationMenuItemsGridCellProvider extends GridCellProvider {
	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {

		return parent::getCellActions($request, $row, $column, $position);
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$navigationMenuItem = $row->getData();
		$columnId = $column->getId();
		assert(is_a($navigationMenuItem, 'NavigationMenuItem') && !empty($columnId));

		switch ($columnId) {
			case 'title':
				$templateMgr = TemplateManager::getManager(Application::get()->getRequest());
				import('classes.core.Services');
				Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

				return array('label' => $navigationMenuItem->getLocalizedTitle());
			default:
				break;
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}
