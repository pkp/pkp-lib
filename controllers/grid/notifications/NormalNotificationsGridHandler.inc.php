<?php

/**
 * @file controllers/grid/notifications/NormalNotificationsGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NormalNotificationsGridHandler
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of notifications for a given user
 */

// Import UI base classes.
import('lib.pkp.controllers.grid.notifications.NotificationsGridHandler');

class NormalNotificationsGridHandler extends NotificationsGridHandler {

	/**
	 * Constructor
	 */
	function NormalNotificationsGridHandler() {
		parent::NotificationsGridHandler();
	}

	/**
	 * @copydoc GridHandler::loadData()
	 * @return array Grid data.
	 */
	protected function loadData($request, $filter) {
		$user = $request->getUser();

		// Get all level normal notifications.
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notifications = $notificationDao->getByUserId($user->getId(), NOTIFICATION_LEVEL_NORMAL);
		return $notifications->toAssociativeArray();
	}

	/**
	 * @copydoc NotificationsGridHandler::getNotificationsColumnTitle()
	 */
	protected function getNotificationsColumnTitle() {
		return 'notification.notifications';
	}
}

?>
