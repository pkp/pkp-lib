<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup notification
 * @see NotificationDAO
 * @brief Class for Notification.
 */

// $Id$

import('notification.NotificationDAO');

class PKPNotification extends DataObject {

	/**
	 * Constructor.
	 */
	function PKPNotification() {
		parent::DataObject();
	}
	
	/**
	 * Create a new notification with the specified arguments and insert into DB
	 * This is a static method
	 * @param $userId int
	 * @param $contents string
	 * @param $param string
	 * @param $location string
	 * @param $isLocalized bool
	 * @param $assocType int
	 * @param $assocId int
	 * @return Notification object
	 */
	function createNotification($userId, $contents, $param, $location, $isLocalized, $assocType) {
		$notification = new Notification();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$notification->setUserId($userId);
		$notification->setContents($contents);
		$notification->setParam($param);
		$notification->setLocation($location);
		$notification->setIsLocalized($isLocalized);
		$notification->setAssocType($assocType);
		$notification->setContext($contextId);
		
		$notificationDao =& DAORegistry::getDAO('NotificationDAO');
		$notificationDao->insertNotification($notification);
		
		return $notification;
	}

	/**
	 * get notification id
	 * @return int
	 */
	function getNotificationId() {
		return $this->getData('notificationId');
	}

	/**
	 * set notification id
	 * @param $commentId int
	 */
	function setNotificationId($notificationId) {
		return $this->setData('notificationId', $notificationId);
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
			$notificationDao->setDateRead($this->getNotificationId());
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
	 * return the path to the icon for this type
	 * @return string
	 */
	function getIconLocation() {
		die ('ABSTRACT CLASS');
	}

 }

?>
