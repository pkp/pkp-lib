<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridRow.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridRow
 * @ingroup controllers_grid_settings_roles
 *
 * @brief User group grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

class UserGroupGridRow extends GridRow {

	/**
	 * Constructor
	 */
	function UserGroupGridRow() {
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		$rowData =& $this->getData(); // a UserGroup object
		assert($rowData != null);

		$rowId = $this->getId();

		// Only add row actions if this is an existing row.
		if (!empty($rowId) && is_numeric($rowId)) {
			$actionArgs = array('userGroupId' => $rowData->getId());
			$router = $request->getRouter();

			$ajaxModal = new AjaxModal($router->url($request, null, null, 'editUserGroup', null, $actionArgs), __('grid.action.edit'), 'modal_edit');
			$editUserGroupLinkAction = new LinkAction(
				'editUserGroup',
				$ajaxModal,
				__('grid.action.edit'),
				'edit'
			);
			$this->addAction($editUserGroupLinkAction);
		}
	}
}

?>
