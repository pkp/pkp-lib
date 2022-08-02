<?php

/**
 * @file classes/notification/NotificationSubscriptionSettingsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationSubscriptionSettingsDAO
 * @ingroup notification
 *
 * @see Notification
 *
 * @brief Operations for retrieving and modifying user's notification settings.
 *  This class stores user settings that determine how notifications should be
 *  delivered to them.
 */

namespace PKP\notification;

class NotificationSubscriptionSettingsDAO extends \PKP\db\DAO
{
    /** @var string The setting which holds the notification status */
    public const BLOCKED_NOTIFICATION_KEY = 'blocked_notification';

    /** @var string The setting which holds the email notification status */
    public const BLOCKED_EMAIL_NOTIFICATION_KEY = 'blocked_emailed_notification';

    /**
     * Delete a notification setting by setting name
     *
     * @param int $notificationId
     * @param int $userId
     * @param string $settingName optional
     */
    public function deleteNotificationSubscriptionSettings($notificationId, $userId, $settingName = null)
    {
        $params = [(int) $notificationId, (int) $userId];
        if ($settingName) {
            $params[] = $settingName;
        }

        return $this->update(
            'DELETE FROM notification_subscription_settings
			WHERE notification_id= ? AND user_id = ?' . isset($settingName) ? '  AND setting_name = ?' : '',
            $params
        );
    }

    /**
     * Retrieve Notification subscription settings by user id
     *
     * @param string $settingName
     * @param int $userId
     * @param int $contextId
     *
     * @return array
     */
    public function &getNotificationSubscriptionSettings($settingName, $userId, $contextId)
    {
        $result = $this->retrieve(
            'SELECT setting_value FROM notification_subscription_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
            [(int) $userId, $settingName, (int) $contextId]
        );

        $settings = [];
        foreach ($result as $row) {
            $settings[] = (int) $row->setting_value;
        }
        return $settings;
    }

    /**
     * Update a user's notification subscription settings
     *
     * @param string $settingName
     * @param array $settings
     * @param int $userId
     * @param int $contextId
     */
    public function updateNotificationSubscriptionSettings($settingName, $settings, $userId, $contextId)
    {
        // Delete old settings first, then insert new settings
        $this->update(
            'DELETE FROM notification_subscription_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
            [(int) $userId, $settingName, (int) $contextId]
        );

        foreach ($settings as $setting) {
            $this->update(
                'INSERT INTO notification_subscription_settings
					(setting_name, setting_value, user_id, context, setting_type)
					VALUES
					(?, ?, ?, ?, ?)',
                [
                    $settingName,
                    (int) $setting,
                    (int) $userId,
                    (int) $contextId,
                    'int'
                ]
            );
        }
    }

    /**
     * Gets a user id by an RSS token value
     *
     * @param int $token
     * @param int $contextId
     *
     * @return int|null
     */
    public function getUserIdByRSSToken($token, $contextId)
    {
        $result = $this->retrieve(
            'SELECT user_id FROM notification_subscription_settings WHERE setting_value = ? AND setting_name = ? AND context = ?',
            [$token, 'token', (int) $contextId]
        );
        $row = $result->current();
        return $row ? $row->user_id : null;
    }

    /**
     * Gets an RSS token for a user id
     *
     * @param int $userId
     * @param int $contextId
     *
     * @return int|null
     */
    public function getRSSTokenByUserId($userId, $contextId)
    {
        $result = $this->retrieve(
            'SELECT setting_value FROM notification_subscription_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
            [(int) $userId, 'token', (int) $contextId]
        );
        $row = $result->current();
        return $row ? $row->setting_value : null;
    }

    /**
     * Generates and inserts a new token for a user's RSS feed
     *
     * @param int $userId
     * @param int $contextId
     *
     * @return int
     */
    public function insertNewRSSToken($userId, $contextId)
    {
        $token = uniqid(rand());

        // Recurse if this token already exists
        if ($this->getUserIdByRSSToken($token, $contextId)) {
            return $this->insertNewRSSToken($userId, $contextId);
        }

        $this->update(
            'INSERT INTO notification_subscription_settings
				(setting_name, setting_value, user_id, context, setting_type)
				VALUES
				(?, ?, ?, ?, ?)',
            [
                'token',
                $token,
                (int) $userId,
                (int) $contextId,
                'string'
            ]
        );

        return $token;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationSubscriptionSettingsDAO', '\NotificationSubscriptionSettingsDAO');
}
