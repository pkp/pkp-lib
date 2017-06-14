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
		$navigationMenuItem =& $row->getData();
		$columnId = $column->getId();
		assert((is_a($navigationMenuItem, 'NavigationMenuItem')) && !empty($columnId));

		return array('label' => $navigationMenuItem->getLocalizedTitle());
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'name':
				$navigationMenuItem = $row->getData();
				$router = $request->getRouter();
				$actionArgs = array('navigationMenuItemId' => $row->getId());

				import('lib.pkp.classes.linkAction.request.AjaxModal');
				return array(new LinkAction(
					'edit',
					new AjaxModal(
						$router->url($request, null, null, 'editNavigationMenuItem', null, $actionArgs),
						__('grid.action.edit'),
						null,
						true),
					__('grid.action.edit')
				),
				new LinkAction(
					'remove',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('common.confirmDelete'),
						__('common.remove'),
						$router->url($request, null, null, 'deleteNavigationMenuItem', null, $actionArgs),
						'modal_delete'
						),
					__('grid.action.remove'),
					'delete'
				));
		}
		return parent::getCellActions($request, $row, $column, $position);
	}
}

?>
