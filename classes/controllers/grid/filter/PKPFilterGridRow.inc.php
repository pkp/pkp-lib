<?php

/**
 * @file classes/controllers/grid/filter/PKPFilterGridRow.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFilterGridRow
 * @ingroup classes_controllers_grid_filter
 *
 * @brief The filter grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class PKPFilterGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function PKPFilterGridRow() {
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		// Do the default initialization
		parent::initialize($request);

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router =& $request->getRouter();
			$actionArgs = array(
				'filterId' => $rowId
			);

			// Add row actions

			// Only add an edit action if the filter actually has
			// settings to be configured.
			$filter =& $this->getData();
			assert(is_a($filter, 'Filter'));
			if ($filter->hasSettings()) {
				$this->addAction(
					new LegacyLinkAction(
						'editFilter',
						LINK_ACTION_MODE_MODAL,
						LINK_ACTION_TYPE_REPLACE,
						$router->url($request, null, null, 'editFilter', null, $actionArgs),
						'grid.action.edit',
						null,
						'edit'
					)
				);
			}
			$this->addAction(
				new LegacyLinkAction(
					'deleteFilter',
					LINK_ACTION_MODE_CONFIRM,
					LINK_ACTION_TYPE_REMOVE,
					$router->url($request, null, null, 'deleteFilter', null, $actionArgs),
					'grid.action.delete',
					null,
					'delete',
					__('manager.setup.filter.grid.confirmDelete', array('filterName' => $filter->getDisplayName()))
				)
			);
		}
	}
}

?>
