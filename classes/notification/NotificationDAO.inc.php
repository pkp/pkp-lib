<?php

/**
 * @file classes/notification/NotificationDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationDAO
 * @ingroup notification
 * @see Notification
 *
 * @brief Operations for retrieving and modifying Notification objects.
 */


import('classes.notification.Notification');

class NotificationDAO extends DAO {
	/**
	 * Constructor.
	 */
	function NotificationDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Notification by notification id
	 * @param $notificationId int
	 * @return object Notification
	 */
	function &getById($notificationId) {
		$result =& $this->retrieve(
			'SELECT * FROM notifications WHERE notification_id = ?', (int) $notificationId
		);

		$notification =& $this->_returnNotificationFromRow($result->GetRowAssoc(false));

		$result->Close();
		unset($result);

		return $notification;
	}

	/**
	 * Retrieve Notifications by user id
	 * Note that this method will not return fully-fledged notification objects.  Use
	 *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
	 * @param $userId int
	 * @param $level int
	 * @param $type int
	 * @param $contextId int
	 * @param $rangeInfo Object
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getByUserId($userId, $level = NOTIFICATION_LEVEL_NORMAL, $type = null, $contextId = null, $rangeInfo = null) {
		$params = array((int) $userId, (int) $level);
		if ($type) $params[] = (int) $type;
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE user_id = ? AND level = ?' . (isset($type) ?' AND type = ?' : '') . (isset($contextId) ?' AND context_id = ?' : '') . ' ORDER BY date_created DESC',
			$params, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnNotificationFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notifications by assoc.
	 * Note that this method will not return fully-fledged notification objects.  Use
	 *  NotificationManager::getNotificationsForUser() to get notifications with URL, and contents
	 * @param $assocType int
	 * @param $assocId int
	 * @param $type int
	 * @param $contextId int
	 * @return object DAOResultFactory containing matching Notification objects
	 */
	function &getByAssoc($assocType, $assocId, $userId = null, $type = null, $contextId = null) {
		$params = array((int) $assocType, (int) $assocId);
		if ($userId) $params[] = (int) $userId;
		if ($contextId) $params[] = (int) $contextId;
		if ($type) $params[] = (int) $type;

		$result =& $this->retrieveRange(
			'SELECT * FROM notifications WHERE assoc_type = ? AND assoc_id = ?' . (isset($userId) ?' AND user_id = ?' : '') . (isset($contextId) ?' AND context_id = ?' : '') . (isset($type) ?' AND type = ?' : '') . ' ORDER BY date_created DESC',
			$params
		);

		$returner = new DAOResultFactory($result, $this, '_returnNotificationFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notifications by notification id
	 * @param $notificationId int
	 * @param $dateRead date
	 * @return boolean
	 */
	function setDateRead($notificationId, $dateRead = null) {
		$dateRead = isset($dateRead) ? $dateRead : Core::getCurrentDate();

		$this->update(
			sprintf('UPDATE notifications
				SET date_read = %s
				WHERE notification_id = ?',
				$this->datetimeToDB($dateRead)),
			(int) $notificationId
		);

		return $dateRead;
	}

	/**
	 * Instantiate and return a new data object.
	 * @return Notification
	 */
	function newDataObject() {
		return new Notification();
	}

	/**
	 * Creates and returns an notification object from a row
	 * @param $row array
	 * @return Notification object
	 */
	function &_returnNotificationFromRow($row) {
		$notification = $this->newDataObject();
		$notification->setId($row['notification_id']);
		$notification->setUserId($row['user_id']);
		$notification->setLevel($row['level']);
		$notification->setDateCreated($this->datetimeFromDB($row['date_created']));
		$notification->setDateRead($this->datetimeFromDB($row['date_read']));
		$notification->setContextId($row['context_id']);
		$notification->setType($row['type']);
		$notification->setAssocType($row['assoc_type']);
		$notification->setAssocId($row['assoc_id']);

		HookRegistry::call('NotificationDAO::_returnNotificationFromRow', array(&$notification, &$row));

		return $notification;
	}

	/**
	 * Inserts a new notification into notifications table
	 * @param $notification object
	 * @return int Notification Id
	 */
	function insertObject(&$notification) {
		$this->update(
			sprintf('INSERT INTO notifications
					(user_id, level, date_created, context_id, type, assoc_type, assoc_id)
				VALUES
					(?, ?, %s, ?, ?, ?, ?)',
				$this->datetimeToDB(Core::getCurrentDate())),
			array(
				(int) $notification->getUserId(),
				(int) $notification->getLevel(),
				(int) $notification->getContextId(),
				(int) $notification->getType(),
				(int) $notification->getAssocType(),
				(int) $notification->getAssocId()
			)
		);
		$notification->setId($this->getInsertNotificationId());

		return $notification->getId();
	}

	/**
	 * Inserts or update a notification into notifications table.
	 * @param $notification Notification
	 * @return int
	 */
	function build(&$notification) {
		$this->update('DELETE FROM notifications
			WHERE context_id = ? AND level = ? AND type = ? AND user_id = ?
				AND assoc_type = ? AND assoc_id = ?',
			array(
				(int) $notification->getContextId(),
				(int) $notification->getLevel(),
				(int) $notification->getType(),
				$notification->getUserId(),
				$notification->getAssocType(),
				$notification->getAssocId()
			)
		);
		$this->insertObject($notification);
	}

	/**
	 * Delete Notification by notification id
	 * @param $notificationId int
	 * @param $userId int
	 * @return boolean
	 */
	function deleteById($notificationId, $userId = null) {
		$params = array((int) $notificationId);
		if (isset($userId)) $params[] = (int) $userId;
		$this->update(
			'DELETE FROM notifications WHERE notification_id = ?' . (isset($userId) ? ' AND user_id = ?' : ''),
			$params
		);
		if ($this->getAffectedRows()) {
			// If a notification was deleted (possibly validating
			// $userId in the process) delete associated settings.
			$notificationSettingsDao =& DAORegistry::getDAO('NotificationSettingsDAO'); /* @var $notificationSettingsDaoDao NotificationSettingsDAO */
			$notificationSettingsDao->deleteSettingsByNotificationId($notificationId);
			return true;
		}
		return false;
	}

	/**
	 * Delete Notification
	 * @param $notification Notification
	 * @return boolean
	 */
	function deleteObject($notification) {
		return $this->deleteById($notification->getId());
	}

	/**
	 * Delete notification(s) by association
	 * @param $assocType int
	 * @param $assocId int
	 * @param $userId int optional
	 * @param $type int optional
	 * @param $contextId int optional
	 * @return boolean
	 */
	function deleteByAssoc($assocType, $assocId, $userId = null, $type = null, $contextId = null) {
		$notificationsFactory =& $this->getByAssoc($assocType, $assocId, $userId = null, $type = null, $contextId = null);
		while ($notification =& $notificationsFactory->next()) {
			$this->deleteObject($notification);
			unset($notification);
		}
	}

	/**
	 * Get the ID of the last inserted notification
	 * @return int
	 */
	function getInsertNotificationId() {
		return $this->getInsertId('notifications', 'notification_id');
	}

	/**
	 * Get the number of unread messages for a user
	 * @param $read boolean Whether to check for read (true) or unread (false) notifications
	 * @param $contextId int
	 * @param $userId int
	 * @param $level int
	 * @return int
	 */
	function getNotificationCount($read = true, $userId, $contextId = null, $level = NOTIFICATION_LEVEL_NORMAL) {
		$params = array((int) $userId, (int) $level);
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieve(
			'SELECT count(*) FROM notifications WHERE user_id = ? AND date_read IS' . ($read ? ' NOT' : '') . ' NULL AND level = ?'
			. (isset($contextId) ? ' AND context_id = ?' : ''),
			$params
		);

		$returner = $result->fields[0];

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Transfer the notifications for a user.
	 * @param $oldUserId int
	 * @param $newUserId int
	 */
	function transferNotifications($oldUserId, $newUserId) {
		$this->update(
				'UPDATE notifications SET user_id = ? WHERE user_id = ?',
				array($newUserId, $oldUserId)
		);
	}
}

?>
