<?php

/**
 * @file controllers/grid/notifications/NotificationsGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationsGridRow
 * @ingroup classes_controllers_grid_notifications
 *
 * @brief A row containing a notification.
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class NotificationsGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function NotificationsGridRow() {
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

		// Is this a new row or an existing row?
		$rowId = $this->getId();

		if (!empty($rowId) && is_numeric($rowId)) {
			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			$router = $request->getRouter();
			$this->addAction(
				new LinkAction(
					'deleteNotification',
					new RemoteActionConfirmationModal(
						__('common.confirmDelete'),
						__('grid.action.delete'),
						$router->url($request, null, null, 'deleteNotification', null, array('notificationId' => $rowId)), 'modal_delete'
					),
					__('grid.action.delete'),
					'delete'
				)
			);
		}
	}
}

?>
