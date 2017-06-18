<?php

/**
 * @file controllers/listbuilder/navigationMenus/NavigationMenuItemsListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsListbuilderGridCellProvider
 * @ingroup controllers_listbuilder_navigationMenus
 *
 * @brief NavigationMenuItems listbuilder cell provider.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class NavigationMenuItemsListbuilderGridCellProvider extends GridCellProvider {

	//
	// Template methods from GridCellProvider
	//
	/**
	 * This implementation assumes a simple data element array that
	 * has column ids as keys.
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$navigationMenuItem = $row->getData();
		$columnId = $column->getId();
		assert((is_a($navigationMenuItem, 'NavigationMenuItem')) && !empty($columnId));
		switch ($columnId) {
			case 'name':
				return array(
					//'labelKey' => $file->getFileId(),
					'label' => $navigationMenuItem->getLocalizedTitle()
				);
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}

?>
