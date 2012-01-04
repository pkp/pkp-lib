<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup notification
 * @see NotificationDAO
 * @brief Class for Notification.
 */

// $Id$

import('notification.NotificationDAO');

define('NOTIFICATION_LEVEL_TRIVIAL',				0x0000001);
define('NOTIFICATION_LEVEL_NORMAL',				0x0000002);

/** Notification associative types. */
define('NOTIFICATION_TYPE_SUCCESS', 				0x0000001);
define('NOTIFICATION_TYPE_WARNING', 				0x0000002);
define('NOTIFICATION_TYPE_ERROR', 				0x0000003);
define('NOTIFICATION_TYPE_FORBIDDEN', 				0x0000004);
define('NOTIFICATION_TYPE_INFORMATION',				0x0000005);
define('NOTIFICATION_TYPE_HELP', 				0x0000006);

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
	 * Also sets setisUnread() if $dateRead is null
	 * @param $dateRead date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateRead($dateRead) {
		if(!isset($dateRead)) {
			$this->setIsUnread(true);
			$notificationDao =& DAORegistry::getDAO('NotificationDAO');
			$notificationDao->setDateRead($this->getId());
		} else {
			$this->setIsUnread(false);
			return $this->setData('dateRead', $dateRead);
		}
	}

	/**
	 * return true if reading for the first time
	 * @return bool
	 */
	function getIsUnread() {
		return $this->getData('isUnread');
	}

	/**
	 * set to true if notification has not been read
	 * @param $isUnread bool
	 */
	function setIsUnread($isUnread) {
		return $this->setData('isUnread', $isUnread);
	}

	/**
	 * get notification title
	 * @return string
	 */
	function getTitle() {
		return $this->getData('title');
	}

	/**
	 * set notification title
	 * @param $contents int
	 */
	function setTitle($title) {
		return $this->setData('title', $title);
	}

	/**
	 * get notification contents
	 * @return string
	 */
	function getContents() {
		return $this->getData('contents');
	}

	/**
	 * set notification contents
	 * @param $contents int
	 */
	function setContents($contents) {
		return $this->setData('contents', $contents);
	}

	/**
	 * get optional parameter (e.g. article title)
	 * @return string
	 */
	function getParam() {
		return $this->getData('param');
	}

	/**
	 * set optional parameter
	 * @param $param int
	 */
	function setParam($param) {
		return $this->setData('param', $param);
	}

	/**
	 * get URL that notification refers to
	 * @return int
	 */
	function getLocation() {
		return $this->getData('location');
	}

	/**
	 * set URL that notification refers to
	 * @param $location int
	 */
	function setLocation($location) {
		return $this->setData('location', $location);
	}

	/**
	 * return true if message is localized (i.e. a system message)
	 * @return int
	 */
	function getIsLocalized() {
		return $this->getData('isLocalized');
	}

	/**
	 * set to true if message is localized (i.e. is a system message)
	 * @param $isLocalized int
	 */
	function setIsLocalized($isLocalized) {
		return $this->setData('isLocalized', $isLocalized);
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
	 * get context id
	 * @return int
	 */
	function getContext() {
		return $this->getData('context');
	}

	/**
	 * set context id
	 * @param $context int
	 */
	function setContext($context) {
		return $this->setData('context', $context);
	}

	/**
	 * get notification style class 
	 * @return string
	 */
	function getStyleClass() {
		switch ($this->getAssocType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifySuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyError';
			case NOTIFICATION_TYPE_INFO: return 'notifyInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyHelp';
		}
	}

	/**
	 * get notification icon style class 
	 * @return string
	 */
	function getIconClass() {
		switch ($this->getAssocType()) {
			case NOTIFICATION_TYPE_SUCCESS: return 'notifyIconSuccess';
			case NOTIFICATION_TYPE_WARNING: return 'notifyIconWarning';
			case NOTIFICATION_TYPE_ERROR: return 'notifyIconError';
			case NOTIFICATION_TYPE_INFO: return 'notifyIconInfo';
			case NOTIFICATION_TYPE_FORBIDDEN: return 'notifyIconForbidden';
			case NOTIFICATION_TYPE_HELP: return 'notifyIconHelp';
		}
	}

	/**
	 * return the path to the icon for this type
	 * @return string
	 */
	function getIconLocation() {
		die ('ABSTRACT CLASS');
	}
}

?>
