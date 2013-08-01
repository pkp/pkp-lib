<?php

/**
 * @file controllers/grid/admin/mergeUsers/MergeUsersGridRow.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MergeUsersGridRow
 * @ingroup controllers_grid_admin_mergeUsers
 *
 * @brief Grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.RedirectConfirmationModal');

class MergeUsersGridRow extends GridRow {

	/** the user id of the old user to remove */
	var $_oldUserId;

	/**
	 * Constructor
	 */
	function MergeUsersGridRow($oldUserId = null) {
		$this->_oldUserId = $oldUserId;
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

		// Is this a new row or an existing row?
		$element =& $this->getData();
		assert(is_a($element, 'User'));

		$rowId = $this->getId();

		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$dispatcher = $request->getDispatcher();
			if ($this->getOldUserId()) {
				$actionArgs = array(
					'oldUserId' => $this->getOldUserId(),
					'newUserId' => $rowId,
				);

				$userDao = DAORegistry::getDAO('UserDAO');
				$oldUser =& $userDao->getById($this->getOldUserId());
				$this->addAction(
					new LinkAction(
						'mergeUser',
						new RedirectConfirmationModal(
							__('admin.mergeUsers.confirm', array('oldUsername' => $oldUser->getUsername(), 'newUsername' => $element->getUsername())),
							null,
							$dispatcher->url($request, ROUTE_PAGE, null, 'admin', 'mergeUsers', null, $actionArgs),
							'modal_merge_users'
					),
					__('admin.mergeUsers.mergeIntoUser'),
					'merge_users')
				);

			} else {
				$actionArgs = array(
					'oldUserId' => $rowId,
				);
				if ($rowId > 1) {  // do not allow the deletion of the admin account.
					$this->addAction(
						new LinkAction(
							'mergeUser',
							new RedirectConfirmationModal(
								__('admin.mergeUsers.mergeUserSelect.confirm'),
								null,
								$dispatcher->url($request, ROUTE_PAGE, null, 'admin', 'mergeUsers', null, $actionArgs),
								'modal_merge_users'
						),
						__('admin.mergeUsers.mergeUser'),
						'merge_users')
					);
				}
			}
		}
	}

	/**
	 * Returns the stored user id of the user to be removed.
	 * @return int the user id.
	 */
	function getOldUserId() {
		return $this->_oldUserId;
	}
}
?>
