<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup notification
 * @see NotificationDAO
 * @brief Class for Notification.
 */

import('lib.pkp.classes.notification.NotificationDAO');

define('UNSUBSCRIBED_USER_NOTIFICATION',			0);

/** Notification levels.  Determines notification behavior **/
define('NOTIFICATION_LEVEL_TRIVIAL',				0x0000001);
define('NOTIFICATION_LEVEL_NORMAL',				0x0000002);
define('NOTIFICATION_LEVEL_TASK',				0x0000003);

/** Notification types.  Determines what text and URL to display for notification */
define('NOTIFICATION_TYPE_SUCCESS',				0x0000001);
define('NOTIFICATION_TYPE_WARNING',				0x0000002);
define('NOTIFICATION_TYPE_ERROR',				0x0000003);
define('NOTIFICATION_TYPE_FORBIDDEN',				0x0000004);
define('NOTIFICATION_TYPE_INFORMATION',				0x0000005);
define('NOTIFICATION_TYPE_HELP',				0x0000006);
define('NOTIFICATION_TYPE_FORM_ERROR',				0x0000007);
define('NOTIFICATION_TYPE_NEW_ANNOUNCEMENT',			0x0000008);

define('NOTIFICATION_TYPE_LOCALE_INSTALLED',			0x4000001);

define('NOTIFICATION_TYPE_PLUGIN_ENABLED',			0x5000001);
define('NOTIFICATION_TYPE_PLUGIN_DISABLED',			0x5000002);

define('NOTIFICATION_TYPE_PLUGIN_BASE',				0x6000001);

class PKPNotification extends DataObject {
	/**
	 * Constructor.
	 */
	function PKPNotification() {
		parent::DataObject();
	}

	/**
	 * get notification id
	 * @return int
	 */
	function getNotificationId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * set notification id
	 * @param $commentId int
	 */
	function setNotificationId($notificationId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($notificationId);
	}

	/**
	 * get user id associated with this notification
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * set user id associated with this notification
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * Get the level (NOTIFICATION_LEVEL_...) for this notification
	 * @return int
	 */
	function getLevel() {
		return $this->getData('level');
	}

	/**
	 * Set the level (NOTIFICATION_LEVEL_...) for this notification
	 * @param $level int
	 */
	function setLevel($level) {
		return $this->setData('level', $level);
	}

	/**
	 * get date notification was created
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateCreated() {
		return $this->getData('dateCreated');
	}

	/**
	 * set date notification was created
	 * @param $dateCreated date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateCreated($dateCreated) {
		return $this->setData('dateCreated', $dateCreated);
	}

	/**
	 * get date notification is read by user
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateRead() {
		return $this->getData('dateRead');
	}

	/**
	 * set date notification is read by user
	 * @param $dateRead date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateRead($dateRead) {
		return $this->setData('dateRead', $dateRead);
	}

	/**
	 * get notification type
	 * @return int
	 */
	function getType() {
		return $this->getData('type');
	}

	/**
	 * set notification type
	 * @param $type int
	 */
	function setType($type) {
		return $this->setData('type', $type);
	}

	/**
	 * get notification type
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * set notification type
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * get notification assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * set notification assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * get context id
	 * @return int
	 */
	function getContextId() {
		return $this->getData('context_id');
	}

	/**
	 * set context id
	 * @param $context int
	 */
	function setContextId($contextId) {
		return $this->setData('context_id', $contextId);
	}
}

?>
