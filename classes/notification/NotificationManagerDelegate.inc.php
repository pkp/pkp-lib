<?php

/**
 * @file classes/notification/NotificationManagerDelegate.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
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


	//
	// Public methods to be overriden by subclasses.
	//
	/**
	 * @copydoc INotificationInfoProvider::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationContents()
	 */
	public function getNotificationContents($request, $notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::getNotificationTitle()
	 */
	public function getNotificationTitle($notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::getStyleClass()
	 */
	public function getStyleClass($notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::getIconClass()
	 */
	public function getIconClass($notification) {
		return '';
	}

	/**
	 * @copydoc INotificationInfoProvider::isVisibleToAllUsers()
	 */
	public function isVisibleToAllUsers($notificationType, $assocType, $assocId) {
		return false;
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
}

?>
