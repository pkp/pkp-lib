<?php

/**
 * @file classes/notification/NotificationSettingsDAODAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationSettingsDAO
 * @ingroup notification
 * @see Notification
 *
 * @brief Operations for retrieving and modifying user's notification settings.
 */

// $Id$

class NotificationSettingsDAO extends DAO {
	/**
	 * Constructor.
	 */
	function NotificationSettingsDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Notifications settings by user id
	 * Returns an array of notification types that the user
	 * does NOT want to be notified of
	 * @param $userId int
	 * @return array
	 */
	function &getNotificationSettings($userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$notificationSettings = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND product = ? AND setting_name = ? AND context = ?',
				array((int) $userId, $productName, 'notify', (int) $contextId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$notificationSettings[] = (int) $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $notificationSettings;
	}

	/**
	 * Retrieve Notifications email settings by user id
	 * Returns an array of notification types that the user
	 * DOES want to be emailed about
	 * @param $userId int
	 * @return array
	 */
	function &getNotificationEmailSettings($userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$emailSettings = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND product = ? AND setting_name = ? AND context = ?',
				array((int) $userId, $productName, 'email', (int) $contextId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$emailSettings[] = (int) $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $emailSettings;
	}

	/**
	 * Update a user's notification settings
	 * @param $notificationSettings array
	 * @param $userId int
	 */
	function updateNotificationSettings($notificationSettings, $userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		// Delete old settings first, then insert new settings
		$this->update('DELETE FROM notification_settings WHERE user_id = ? AND product = ? AND setting_name = ? AND context = ?',
			array((int) $userId, $productName, 'notify', (int) $contextId));

		for ($i=0; $i<count($notificationSettings); $i++) {
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, product, context)
					VALUES
					(?, ?, ?, ?, ?)',
				array(
					"notify",
					$notificationSettings[$i],
					(int) $userId,
					$productName,
					(int) $contextId
				)
			);
		}
	}

	/**
	 * Update a user's notification email settings
	 * @param $notificationEmailSettings array
	 * @param $userId int
	 */
	function updateNotificationEmailSettings($emailSettings, $userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		// Delete old settings first, then insert new settings
		$this->update('DELETE FROM notification_settings WHERE user_id = ? AND product = ? AND setting_name = ? AND context = ?',
			array($userId, $productName, 'email', $contextId));

		for ($i=0; $i<count($emailSettings); $i++) {
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, product, context)
					VALUES
					(?, ?, ?, ?, ?)',
				array(
					"email",
					$emailSettings[$i],
					(int) $userId,
					$productName,
					(int) $contextId
				)
			);
		}
	}

	/**
	 * Gets a user id by an RSS token value
	 * @param $token int
	 * @return int
	 */
	function getUserIdByRSSToken($token) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$result =& $this->retrieve(
			'SELECT user_id FROM notification_settings WHERE setting_value = ? AND setting_name = ? AND product = ? AND context = ?',
				array($token, 'token', $productName, (int) $contextId)
		);

		$row = $result->GetRowAssoc(false);
		$userId = $row['user_id'];

		$result->Close();
		unset($result);

		return $userId;
	}

	/**
	 * Gets an RSS token for a user id
	 * @param $userId int
	 * @return int
	 */
	function getRSSTokenByUserId($userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE user_id = ? AND setting_name = ? AND product = ? AND context = ?',
				array((int) $userId, 'token', $productName, (int) $contextId)
		);

		$row = $result->GetRowAssoc(false);
		$userId = $row['setting_value'];

		$result->Close();
		unset($result);

		return $userId;
	}

	/**
	 * Generates and inserts a new token for a user's RSS feed
	 * @param $userId int
	 * @return int
	 */
	function insertNewRSSToken($userId) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$token = uniqid(rand());

		$this->update(
			'INSERT INTO notification_settings
				(setting_name, setting_value, user_id, product, context)
				VALUES
				(?, ?, ?, ?, ?)',
			array(
				'token',
				$token,
				(int) $userId,
				$productName,
				(int) $contextId
			)
		);

		return $token;
	}

	/**
	 * Generates an access key for the guest user and adds them to the settings table
	 * @param $userId int
	 * @return int
	 */
	function subscribeGuest($email) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		// Check that the email doesn't already exist
		$result =& $this->retrieve(
			'SELECT * FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND product = ? AND context = ?',
			array(
				'mailList',
				$email,
				$productName,
				(int) $contextId
			)
		);

		if ($result->RecordCount() != 0) {
			return false;
		} else {
			$this->update(
				'DELETE FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND user_id = ? AND product = ? AND context = ?',
				array(
					'mailListUnconfirmed',
					$email,
					0,
					$productName,
					(int) $contextId
				)
			);
			$this->update(
				'INSERT INTO notification_settings
					(setting_name, setting_value, user_id, product, context)
					VALUES
					(?, ?, ?, ?, ?)',
				array(
					'mailListUnconfirmed',
					$email,
					0,
					$productName,
					(int) $contextId
				)
			);
		}

		// Get assoc_id into notification_settings table, also used as user_id for access key
		$assocId = $this->getInsertNotificationSettingId();

		import('security.AccessKeyManager');
		$accessKeyManager = new AccessKeyManager();

		$password = $accessKeyManager->createKey('MailListContext', $assocId, $assocId, 60); // 60 days
		return $password;
	}

	/**
	 * Removes an email address and associated access key from email notifications
	 * @param $email string
	 * @param $password string
	 * @return boolean
	 */
	function unsubscribeGuest($email, $password) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND product = ? AND context = ?',
			array(
				'mailList',
				$productName,
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$userId = (int) $row['setting_id'];

		$result->Close();
		unset($result);

		import('security.AccessKeyManager');
		$accessKeyManager = new AccessKeyManager();
		$accessKeyHash = AccessKeyManager::generateKeyHash($password);
		$accessKey = $accessKeyManager->validateKey('MailListContext', $userId, $accessKeyHash);

		if ($accessKey) {
			$this->update(
				'DELETE FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND product = ? AND context = ?',
				array(
					'mailList',
					$email,
					$productName,
					(int) $contextId
				)
			);
			$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
			$accessKeyDao->deleteObject($accessKey);
			return true;
		} else return false;
	}

	/**
	 * Gets the setting id for a maillist member (to access the accompanying access key)
	 * @return array
	 */
	function getMailListSettingId($email, $settingName = 'mailListUnconfirmed') {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND product = ? AND context = ?',
			array(
				$settingName,
				$email,
				$productName,
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$settingId = (int) $row['setting_id'];

		return $settingId;
	}

	/**
	 * Update the notification settings table to confirm the mailing list subscription
	 * @return boolean
	 */
	function confirmMailListSubscription($settingId) {
		return $this->update(
			'UPDATE notification_settings SET setting_name = ? WHERE setting_id = ?',
			array('mailList', (int) $settingId)
		);
	}

	/**
	 * Gets a list of email addresses of users subscribed to the mailing list
	 * @return array
	 */
	function getMailList() {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();
		$mailList = array();

		$result =& $this->retrieve(
			'SELECT setting_value FROM notification_settings WHERE setting_name = ? AND product = ? AND context = ?',
			array(
				'mailList',
				$productName,
				(int) $contextId
			)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$mailList[] = $row['setting_value'];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $mailList;
	}

	/**
	 * Generates and inserts a new password for a mailing list user
	 * @param $email string
	 * @return string
	 */
	function resetPassword($email) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context->getId();

		$result =& $this->retrieve(
			'SELECT setting_id FROM notification_settings WHERE setting_name = ? AND setting_value = ? AND product = ? AND context = ?',
			array(
				'mailList',
				$email,
				$productName,
				(int) $contextId
			)
		);

		$row = $result->GetRowAssoc(false);
		$settingId = $row['setting_id'];

		$result->Close();
		unset($result);

		$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
		$accessKey = $accessKeyDao->getAccessKeyByUserId('MailListContext', $settingId);

		if ($accessKey) {
			$key = Validation::generatePassword();
			$accessKey->setKeyHash(md5($key));

			$accessKeyDao =& DAORegistry::getDAO('AccessKeyDAO');
			$accessKeyDao->updateObject($accessKey);
			return $key;
		} else return false;
	}

	/**
	 * Get the ID of the last inserted notification
	 * @return int
	 */
	function getInsertNotificationSettingId() {
		return $this->getInsertId('notification_settings', 'setting_id');
	}

}

?>
