<?php

/**
 * @file controllers/grid/settings/category/CategoryGridRow.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridRow
 * @ingroup controllers_grid_settings_category
 *
 * @brief Category grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class CategoryGridRow extends GridRow {
	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		$rowData = $this->getData(); // a Category object
		assert($rowData != null);

		$rowId = $this->getId();

		// Only add row actions if this is an existing row.
		if (!empty($rowId) && is_numeric($rowId)) {
			$actionArgs = array_merge(
				$this->getRequestArgs(),
				array('categoryId' => $rowData->getId())
			);
			$router = $request->getRouter();

			$this->addAction(new LinkAction(
				'editCategory',
				new AjaxModal(
					$router->url($request, null, null, 'editCategory', null, $actionArgs),
					__('grid.category.edit')
				),
				__('grid.action.edit'),
				'edit'
			));

			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			$this->addAction(new LinkAction(
				'removeCategory',
				new RemoteActionConfirmationModal(
					$request->getSession(),
					__('grid.category.removeText'),
					null,
					$router->url($request, null, null, 'deleteCategory', null, $actionArgs)
				),
				__('grid.action.remove'),
				'delete'
			));
		}
	}
}


