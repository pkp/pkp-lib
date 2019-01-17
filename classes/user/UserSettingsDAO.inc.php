<?php

/**
 * @file classes/user/UserSettingsDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserSettingsDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying user settings.
 */


class UserSettingsDAO extends DAO {
	/**
	 * Retrieve a user setting value.
	 * @param $userId int
	 * @param $name
	 * @param $contextId int
	 * @return mixed
	 * @see UserSettingsDAO::getByAssoc
	 */
	function getSetting($userId, $name, $contextId = null) {
		return $this->getByAssoc($userId, $name, Application::getContextAssocType(), $contextId);
	}

	/**
	 * Retrieve all users by setting name and value.
	 * @param $name string
	 * @param $value mixed
	 * @param $type string
	 * @param $contextId int
	 * @return DAOResultFactory matching Users
	 * @see UserSettingsDAO::getUsersByAssocSetting
	 */
	function getUsersBySetting($name, $value, $type = null, $contextId = null) {
		return $this->getUsersByAssocSetting($name, $value, $type, Application::getContextAssocType(), $contextId);
	}

	/**
	 * Retrieve all settings for a user for a journal.
	 * @param $userId int
	 * @param $contextId int
	 * @return array 
	 */
	function getSettingsByContextId($userId, $contextId = null) {
		return $this->getSettingsByAssoc($userId, Application::getContextAssocType(), $contextId);
	}

	/**
	 * Add/update a user setting.
	 * @param $userId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $contextId int
	 * @see UserSettingsDAO::updateByAssoc
	 */
	function updateSetting($userId, $name, $value, $type = null, $contextId = null) {
		return $this->updateByAssoc($userId, $name, $value, $type, Application::getContextAssocType(), $contextId);
	}

	/**
	 * Delete a user setting by association.
	 * @param $userId int
	 * @param $name string
	 * @param $contextId int
	 * @see UserSettingsDAO::deleteByAssoc
	 */
	function deleteSetting($userId, $name, $contextId = null) {
		return $this->deleteByAssoc($userId, $name, Application::getContextAssocType(), $contextId);
	}

	/**
	 * Retrieve a user setting value by association.
	 * @param $userId int
	 * @param $name
	 * @param $assocType int
	 * @param $assocId int
	 * @return mixed
	 */
	function getByAssoc($userId, $name, $assocType = null, $assocId = null) {
		$result = $this->retrieve(
			'SELECT	setting_value,
				setting_type
			FROM	user_settings
			WHERE	user_id = ? AND
				setting_name = ? AND
				assoc_type = ? AND
				assoc_id = ?',
			array(
				(int) $userId,
				$name,
				(int) $assocType,
				(int) $assocId
			)
		);

		if ($result->RecordCount() != 0) {
			$row = $result->getRowAssoc(false);
			$returner = $this->convertFromDB($row['setting_value'], $row['setting_type']);
		} else {
			$returner = null;
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all users by association, setting name, and value.
	 * @param $name string
	 * @param $value mixed
	 * @param $type string
	 * @param $assocType int
	 * @param $assocId int
	 * @return DAOResultFactory matching Users
	 */
	function getUsersByAssocSetting($name, $value, $type = null, $assocType = null, $assocId = null) {
		$userDao = DAORegistry::getDAO('UserDAO');

		$value = $this->convertToDB($value, $type);
		$result = $this->retrieve(
			'SELECT	u.*
			FROM	users u,
				user_settings s
			WHERE	u.user_id = s.user_id AND
				s.setting_name = ? AND
				s.setting_value = ? AND
				s.assoc_type = ? AND
				s.assoc_id = ?',
			array($name, $value, (int) $assocType, (int) $assocId)
		);

		return new DAOResultFactory($result, $userDao, '_returnUserFromRow');
	}

	/**
	 * Retrieve all settings for a user by association info.
	 * @param $userId int
	 * @param $assocType int
	 * @param $assocId int
	 * @return array
	 */
	function getSettingsByAssoc($userId, $assocType = null, $assocId = null) {
		$userSettings = array();

		$result = $this->retrieve(
			'SELECT	setting_name,
				setting_value,
				setting_type
			FROM	user_settings
			WHERE	user_id = ? AND
				assoc_type = ?
				AND assoc_id = ?',
			array((int) $userId, (int) $assocType, (int) $assocId)
		);

		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$value = $this->convertFromDB($row['setting_value'], $row['setting_type']);
			$userSettings[$row['setting_name']] = $value;
			$result->MoveNext();
		}
		$result->Close();
		return $userSettings;
	}

	/**
	 * Add/update a user setting by association.
	 * @param $userId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $assocType int
	 * @param $assocId int
	 */
	function updateByAssoc($userId, $name, $value, $type = null, $assocType = null, $assocId = null) {
		$result = $this->retrieve(
			'SELECT	COUNT(*)
			FROM	user_settings
			WHERE	user_id = ? AND
				setting_name = ?
				AND assoc_type = ?
				AND assoc_id = ?',
			array((int) $userId, $name, (int) $assocType, (int) $assocId)
		);

		$value = $this->convertToDB($value, $type);
		if ($result->fields[0] == 0) {
			$returner = $this->update(
				'INSERT INTO user_settings
					(user_id, setting_name, assoc_type, assoc_id, setting_value, setting_type)
				VALUES
					(?, ?, ?, ?, ?, ?)',
				array(
					(int) $userId,
					$name,
					(int) $assocType,
					(int) $assocId,
					$value,
					$type
				)
			);
		} else {
			$returner = $this->update(
				'UPDATE user_settings
				SET	setting_value = ?,
					setting_type = ?
				WHERE	user_id = ? AND
					setting_name = ? AND
					assoc_type = ?
					AND assoc_id = ?',
				array(
					$value,
					$type,
					(int) $userId,
					$name,
					(int) $assocType,
					(int) $assocId
				)
			);
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Delete a user setting by association.
	 * @param $userId int
	 * @param $name string
	 * @param $assocType int
	 * @param $assocId int
	 */
	function deleteByAssoc($userId, $name, $assocType = null, $assocId = null) {
		return $this->update(
			'DELETE FROM user_settings WHERE user_id = ? AND setting_name = ? AND assoc_type = ? AND assoc_id = ?',
			array((int) $userId, $name, (int) $assocType, (int) $assocId)
		);
	}

	/**
	 * Delete all settings for a user.
	 * @param $userId int
	 */
	function deleteSettings($userId) {
		return $this->update(
			'DELETE FROM user_settings WHERE user_id = ?', (int) $userId
		);
	}
}

