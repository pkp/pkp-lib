<?php

/**
 * @file controllers/grid/settings/contributor/ContributorGridRow.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContributorGridRow
 * @ingroup controllers_grid_settings_contributor
 *
 * @brief Handle contributor grid row requests.
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class ContributorGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function ContributorGridRow() {
		parent::GridRow();
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);
		// add Grid Row Actions

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Actions
			$router = $request->getRouter();
			$actionArgs = array(
				'gridId' => $this->getGridId(),
				'rowId' => $rowId
			);

			$this->addAction(
				new LinkAction(
					'editContributor',
					new AjaxModal(
						$router->url($request, null, null, 'editContributor', null, $actionArgs),
						__('grid.action.edit'),
						'modal_edit',
						true
						),
					__('grid.action.edit'),
					'edit')
			);

			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

			$this->addAction(
				new LinkAction(
					'deleteContributor',
					new RemoteActionConfirmationModal(
						__('grid.action.delete'),
						__('common.delete'),
						$router->url($request, null, null, 'deleteContributor', null, $actionArgs),
						'modal_delete'
					),
					__('grid.action.delete'),
					'delete')
			);
		}
	}
}

?>
