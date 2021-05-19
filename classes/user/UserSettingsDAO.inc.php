<?php

/**
 * @file classes/user/UserSettingsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSettingsDAO
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying user settings.
 */

namespace PKP\user;

use APP\core\Application;
use PKP\db\DAORegistry;

use PKP\db\DAOResultFactory;

class UserSettingsDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a user setting value.
     *
     * @param $userId int
     * @param $name
     * @param $contextId int
     *
     * @see UserSettingsDAO::getByAssoc
     */
    public function getSetting($userId, $name, $contextId = \PKP\core\PKPApplication::CONTEXT_SITE)
    {
        $result = $this->retrieve(
            'SELECT	setting_value,
				setting_type
			FROM	user_settings
			WHERE	user_id = ? AND
				setting_name = ? AND
				assoc_type = ? AND
				assoc_id = ?',
            [
                (int) $userId,
                $name,
                Application::getContextAssocType(),
                (int) $contextId
            ]
        );

        $row = (array) $result->current();
        return $row ? $this->convertFromDB($row['setting_value'], $row['setting_type']) : null;
    }

    /**
     * Retrieve all users by setting name and value.
     *
     * @param $name string
     * @param $value mixed
     * @param $type string
     * @param $contextId int
     *
     * @return DAOResultFactory matching Users
     */
    public function getUsersBySetting($name, $value, $type = null, $contextId = \PKP\core\PKPApplication::CONTEXT_SITE)
    {
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
            [$name, $value, Application::getContextAssocType(), (int) $contextId]
        );

        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        return new DAOResultFactory($result, $userDao, '_returnUserFromRow');
    }

    /**
     * Retrieve all settings for a user for a context.
     *
     * @param $userId int
     * @param $contextId int
     *
     * @return array
     */
    public function getSettingsByContextId($userId, $contextId = \PKP\core\PKPApplication::CONTEXT_SITE)
    {
        $result = $this->retrieve(
            'SELECT	setting_name,
				setting_value,
				setting_type
			FROM	user_settings
			WHERE	user_id = ? AND
				assoc_type = ?
				AND assoc_id = ?',
            [(int) $userId, Application::getContextAssocType(), (int) $contextId]
        );

        $userSettings = [];
        foreach ($result as $row) {
            $value = $this->convertFromDB($row->setting_value, $row->setting_type);
            $userSettings[$row->setting_name] = $value;
        }
        return $userSettings;
    }

    /**
     * Add/update a user setting.
     *
     * @param $userId int
     * @param $name string
     * @param $value mixed
     * @param $type string data type of the setting. If omitted, type will be guessed
     * @param $contextId int
     */
    public function updateSetting($userId, $name, $value, $type = null, $contextId = \PKP\core\PKPApplication::CONTEXT_SITE)
    {
        $result = $this->retrieve(
            'SELECT	COUNT(*) AS row_count
			FROM	user_settings
			WHERE	user_id = ? AND
				setting_name = ?
				AND assoc_type = ?
				AND assoc_id = ?',
            [(int) $userId, $name, Application::getContextAssocType(), (int) $contextId]
        );

        $row = (array) $result->current();
        $value = $this->convertToDB($value, $type);
        if (!$row || $row['row_count'] == 0) {
            $this->update(
                'INSERT INTO user_settings
					(user_id, setting_name, assoc_type, assoc_id, setting_value, setting_type)
				VALUES
					(?, ?, ?, ?, ?, ?)',
                [
                    (int) $userId,
                    $name,
                    Application::getContextAssocType(),
                    (int) $contextId,
                    $value,
                    $type
                ]
            );
        } else {
            $this->update(
                'UPDATE user_settings
				SET	setting_value = ?,
					setting_type = ?
				WHERE	user_id = ? AND
					setting_name = ? AND
					assoc_type = ? AND
					assoc_id = ?',
                [
                    $value,
                    $type,
                    (int) $userId,
                    $name,
                    Application::getContextAssocType(),
                    (int) $contextId
                ]
            );
        }
    }

    /**
     * Delete a user setting by context.
     *
     * @param $userId int
     * @param $name string
     * @param $contextId int
     */
    public function deleteSetting($userId, $name, $contextId = \PKP\core\PKPApplication::CONTEXT_SITE)
    {
        $this->update(
            'DELETE FROM user_settings WHERE user_id = ? AND setting_name = ? AND assoc_type = ? AND assoc_id = ?',
            [(int) $userId, $name, Application::getContextAssocType(), (int) $contextId]
        );
    }

    /**
     * Delete all settings for a user.
     *
     * @param $userId int
     */
    public function deleteSettings($userId)
    {
        return $this->update(
            'DELETE FROM user_settings WHERE user_id = ?',
            [(int) $userId]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\UserSettingsDAO', '\UserSettingsDAO');
}
