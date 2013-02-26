<?php

/**
 * @file classes/controllers/grid/notifications/PKPNotificationsGridCellProvider.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationsGridCellProvider
 * @ingroup controllers_grid_notifications
 *
 * @brief Class for a cell provider that can retrieve labels from notifications
 */


import('lib.pkp.classes.controllers.grid.GridCellProvider');
import('lib.pkp.classes.linkAction.request.RedirectAction');

class PKPNotificationsGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function PKPNotificationsGridCellProvider() {
		parent::GridCellProvider();
	}

	/**
	 * Get cell actions associated with this row/column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions(&$request, &$row, &$column, $position = GRID_ACTION_POSITION_DEFAULT) {
		if ( $column->getId() == 'title' ) {
			return array();
		} elseif ($column->getId() == 'task') {
			$notification = $row->getData();

			$notificationMgr = new NotificationManager();
			return array(new LinkAction(
				'details',
				new RedirectAction(
					$notificationMgr->getNotificationUrl($request, $notification)
				),
				$notificationMgr->getNotificationMessage($request, $notification)
			));
		}
		// This should be unreachable.
		assert(false);
	}
}

?>
