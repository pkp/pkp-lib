<?php

/**
 * @file controllers/grid/notifications/TaskNotificationsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskNotificationsGridHandler
 * @ingroup controllers_grid_notifications
 *
 * @brief Handle the display of task notifications for a given user
 */

// Import UI base classes.
import('lib.pkp.controllers.grid.notifications.NotificationsGridHandler');

class TaskNotificationsGridHandler extends NotificationsGridHandler {

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Basic grid configuration.
		$this->setTitle('common.tasks');
	}

	/**
	 * @see GridHandler::loadData()
	 * @return array Grid data.
	 */
	protected function loadData($request, $filter) {
		$user = $request->getUser();

		// Get all level task notifications.
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notifications = $notificationDao->getByUserId($user->getId(), NOTIFICATION_LEVEL_TASK)->toArray();

		// Checkbox selection requires the array keys match the notification id
		$notificationsForRow = [];
		foreach ($notifications as $notification) {
			$notificationsForRow[$notification->getId()] = $notification;
		}
		return $notificationsForRow;
	}
}


