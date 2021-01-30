<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenusGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusGridCellProvider
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Cell provider for title column of a NavigationMenu grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class NavigationMenusGridCellProvider extends GridCellProvider {
	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'title':
				$navigationMenu = $row->getData();
				$router = $request->getRouter();
				$actionArgs = array('navigationMenuId' => $row->getId());

				import('lib.pkp.classes.linkAction.request.AjaxModal');
				return array(new LinkAction(
					'edit',
					new AjaxModal(
						$router->url($request, null, null, 'editNavigationMenu', null, $actionArgs),
						__('grid.action.edit'),
						null,
						true),
					htmlspecialchars($navigationMenu->getTitle())
				));
		}
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
		$navigationMenu = $row->getData();
		$columnId = $column->getId();
		assert(is_a($navigationMenu, 'NavigationMenu') && !empty($columnId));

		switch ($columnId) {
			case 'title':
				return array('label' => '');
			case 'nmis':
				$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /* @var $navigationMenuItemDao NavigationMenuItemDAO */
				$items = $navigationMenuItemDao->getByMenuId($navigationMenu->getId())->toArray();

				$navigationMenusTitles = '';

				$templateMgr = TemplateManager::getManager(Application::get()->getRequest());
				import('classes.core.Services');
				foreach ($items as $item) {
					Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $item);
					$navigationMenusTitles = $navigationMenusTitles.$item->getLocalizedTitle().', ';
				}

				$navigationMenusTitles = trim($navigationMenusTitles, ', ');

				return array('label' => $navigationMenusTitles);
			default:
				break;
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}
