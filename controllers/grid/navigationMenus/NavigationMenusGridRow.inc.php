<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenusGridRow.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenusGridRow
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief NavigationMenu grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class NavigationMenusGridRow extends GridRow {
	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		$element = $this->getData();
		assert(is_a($element, 'NavigationMenu'));

		$rowId = $this->getId();

		// Is this a new row or an existing row?
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router = $request->getRouter();
			$actionArgs = array(
				'navigationMenuId' => $rowId
			);
			$this->addAction(
				new LinkAction(
					'edit',
					new AjaxModal(
						$router->url($request, null, null, 'editNavigationMenu', null, $actionArgs),
						__('grid.action.edit'),
						'modal_edit',
						true
						),
					__('grid.action.edit'),
					'edit')
			);
			
			$this->addAction(
			new LinkAction(
				'remove',
				new RemoteActionConfirmationModal(
					$request->getSession(),
					__('common.confirmDelete'),
					__('common.remove'),
					$router->url($request, null, null, 'deleteNavigationMenu', null, $actionArgs),
					'modal_delete'
					),
				__('grid.action.remove'),
				'delete')
			);

		}
	}
}

?>
