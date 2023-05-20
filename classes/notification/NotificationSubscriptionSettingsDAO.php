<?php

/**
 * @file classes/notification/NotificationSubscriptionSettingsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationSubscriptionSettingsDAO
 *
 * @ingroup notification
 *
 * @see Notification
 *
 * @brief Operations for retrieving and modifying user's notification settings.
 *  This class stores user settings that determine how notifications should be
 *  delivered to them.
 */

namespace PKP\notification;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     *
     * @return array
     */
    public function &getNotificationSubscriptionSettings($settingName, $userId, ?int $contextId)
    {
        $result = $this->retrieve(
            'SELECT setting_value FROM notification_subscription_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
            [(int) $userId, $settingName, $contextId]
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
     */
    public function updateNotificationSubscriptionSettings($settingName, $settings, $userId, ?int $contextId)
    {
        // Delete old settings first, then insert new settings
        $this->update(
            'DELETE FROM notification_subscription_settings WHERE user_id = ? AND setting_name = ? AND context = ?',
            [(int) $userId, $settingName, $contextId]
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
                    $contextId,
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
     * @return string
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

    /**
     * Retrieves IDs of all users subscribed to the notification of specific type
     *
     * @param string[] $blockedNotificationKey list of the NotificationSubscriptionSettingsDAO::BLOCKED_* constants
     * @param int[] $blockedNotificationType list of the PKPNotification::NOTIFICATION_TYPE_* constants
     */
    public function getSubscribedUserIds(array $blockedNotificationKey, array $blockedNotificationType, array $contextIds, ?array $roleIds = null): Collection
    {
        return DB::table('users as u')->select('u.user_id')
            ->whereNotIn(
                'u.user_id',
                fn (Builder $q) =>
                $q->select('nss.user_id')->from('notification_subscription_settings as nss')
                    ->whereIn('setting_name', $blockedNotificationKey)
                    ->whereIn('setting_value', $blockedNotificationType)
            )->whereExists(
                fn (Builder $q) => $q->from('user_user_groups', 'uug')
                    ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                    ->whereColumn('uug.user_id', '=', 'u.user_id')
                    ->whereIn(DB::raw('COALESCE(ug.context_id, 0)'), $contextIds)
                    ->when(!is_null($roleIds), fn (Builder $q) => $q->whereIn('ug.role_id', $roleIds))
            )->pluck('user_id');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationSubscriptionSettingsDAO', '\NotificationSubscriptionSettingsDAO');
}
