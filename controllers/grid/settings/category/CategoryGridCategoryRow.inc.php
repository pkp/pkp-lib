<?php

/**
 * @file controllers/grid/settings/category/CategoryGridCategoryRow.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridCategoryRow
 * @ingroup controllers_grid_settings_category
 *
 * @brief Category grid category row definition
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

// Link actions
import('lib.pkp.classes.linkAction.request.AjaxModal');

class CategoryGridCategoryRow extends GridCategoryRow {
	//
	// Overridden methods from GridCategoryRow
	//
	/**
	 * @copydoc GridCategoryRow::initialize()
	 */
	function initialize($request, $template = null) {
		// Do the default initialization
		parent::initialize($request, $template);

		// Is this a new row or an existing row?
		$categoryId = $this->getId();
		if (!empty($categoryId) && is_numeric($categoryId)) {
			// Only add row actions if this is an existing row
			$category = $this->getData();
			$router = $request->getRouter();

			$categoryDao = DAORegistry::getDAO('CategoryDAO');
			$childCategories = $categoryDao->getByParentId($categoryId);
			if ($childCategories->getCount() == 0) {
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
				$this->addAction(
					new LinkAction(
						'deleteCategory',
						new RemoteActionConfirmationModal(
							$request->getSession(),
							__('common.confirmDelete'),
							__('common.delete'),
							$router->url($request, null, null, 'deleteCategory', null, array('categoryId' => $categoryId)),
							'modal_delete'
						),
						__('grid.action.remove'),
						'delete'
					)
				);
			}

			$this->addAction(new LinkAction(
				'editCategory',
				new AjaxModal(
					$router->url($request, null, null, 'editCategory', null, array('categoryId' => $categoryId)),
					__('grid.category.edit'),
					'modal_edit'
				),
				$category->getLocalizedTitle()
			), GRID_ACTION_POSITION_ROW_CLICK);
		}
	}

	/**
	 * Category rows only have one cell and one label.  This is it.
	 * return string
	 */
	function getCategoryLabel() {
		return '';
	}
}


