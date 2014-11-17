<?php

/**
 * @file classes/notification/NotificationManagerDelegate.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationManagerDelegate
 * @ingroup notification
 *
 * @brief Abstract class to support notification manager delegates
 * that provide default implementation to the interface that defines
 * notification presentation data. It also introduce a method to be
 * extended by subclasses to update notification objects.
 */

import('lib.pkp.classes.notification.PKPNotificationOperationManager');

abstract class NotificationManagerDelegate extends PKPNotificationOperationManager {

	private $_notificationType;

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $notificationType int
	 */
	function NotificationManagerDelegate($notificationType) {
		$this->_notificationType = $notificationType;

		parent::PKPNotificationOperationManager();
	}

	/**
	 * Get the current notification type this
	 * manager is handling.
	 * @return int
	 */
	protected function getNotificationType() {
		return $this->_notificationType;
	}

	/**
	 * Define operations to update notifications.
	 * @param $request PKPRequest
	 * @param $userIds array
	 * @param $assocType int
	 * @param $assocId int
	 */
	public function updateNotification($request, $userIds, $assocType, $assocId) {
		return false;
	}
	
	/**
	 * Check if this manager delegate can handle the 
	 * creation of the passed notification type.
	 * @copydoc PKPNotificationOperationManager::createNotification()
	 */
	public function createNotification($request, $userId = null, $notificationType, $contextId = null, $assocType = null, $assocId = null, $level = NOTIFICATION_LEVEL_NORMAL, $params = null) {
		if ($notificationType != $this->getNotificationType()) assert(false);
		return parent::createNotification($request, $userId, $notificationType, $contextId, $assocType, $assocId, $level, $params);
	}

	/**
	 * Check if the manager delegate can send to mailing list
	 * the passed type of notification.
	 * @copydoc PKPNotificationOperationManager::sendToMailingList()
	 */
	public function sendToMailingList($request, $notification) {
		if ($notification->getType() !== $this->getNotificationType()) assert(false);
		parent::sendToMailingList($request, $notification);
	}
}

?>
