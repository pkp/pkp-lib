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
use PKP\core\Core;

class NotificationSubscriptionSettingsDAO extends \PKP\db\DAO
{
    /** @var string The setting which holds the notification status */
    public const BLOCKED_NOTIFICATION_KEY = 'blocked_notification';

    /** @var string The setting which holds the email notification status */
    public const BLOCKED_EMAIL_NOTIFICATION_KEY = 'blocked_emailed_notification';

    /**
     * Delete a notification setting by setting name
     */
    public function deleteNotificationSubscriptionSettings(int $notificationId, int $userId, ?string $settingName = null): int
    {
        return DB::table('notification_subscription_settings')
            ->where('notification_id', '=', $notificationId)
            ->where('user_id', '=', $userId)
            ->when(isset($settingName), fn (Builder $q) => $q->where('setting_name', '=', $settingName))
            ->delete();
    }

    /**
     * Retrieve Notification subscription settings by user id
     *
     * @return int[]
     */
    public function getNotificationSubscriptionSettings(string $settingName, int $userId, ?int $contextId): array
    {
        return DB::table('notification_subscription_settings')
            ->where('user_id', '=', $userId)
            ->where('setting_name', '=', $settingName)
            ->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])
            ->pluck('setting_value')
            ->map(fn ($v) => intval($v))
            ->toArray();
    }

    /**
     * Update a user's notification subscription settings
     */
    public function updateNotificationSubscriptionSettings(string $settingName, array $settings, int $userId, ?int $contextId): void
    {
        // Delete old settings first, then insert new settings
        DB::table('notification_subscription_settings')
            ->where('user_id', '=', $userId)
            ->where('setting_name', '=', $settingName)
            ->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])
            ->delete();

        foreach ($settings as $setting) {
            DB::table('notification_subscription_settings')->insert([
                'setting_name' => $settingName,
                'setting_value' => (int) $setting,
                'user_id' => $userId,
                'context_id' => $contextId,
                'setting_type' => 'int'
            ]);
        }
    }

    /**
     * Retrieves IDs of all users subscribed to the notification of specific type
     *
     * @param string[] $blockedNotificationKey list of the NotificationSubscriptionSettingsDAO::BLOCKED_* constants
     * @param int[] $blockedNotificationType list of the Notification::NOTIFICATION_TYPE_* constants
     */
    public function getSubscribedUserIds(array $blockedNotificationKey, array $blockedNotificationType, array $contextIds, ?array $roleIds = null): Collection
    {
        $currentDateTime = Core::getCurrentDate();
        return DB::table('users as u')
            ->select('u.user_id')
            ->whereNotIn(
                'u.user_id',
                fn (Builder $q) => $q->select('nss.user_id')
                    ->from('notification_subscription_settings as nss')
                    ->whereIn('setting_name', $blockedNotificationKey)
                    ->whereIn('setting_value', $blockedNotificationType)
            )->whereExists(
                fn (Builder $q) => $q->from('user_user_groups', 'uug')
                    ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                    ->whereColumn('uug.user_id', '=', 'u.user_id')
                    ->where(fn (Builder $q) => $q->where('uug.date_start', '<=', $currentDateTime)->orWhereNull('uug.date_start'))
                    ->where(fn (Builder $q) => $q->where('uug.date_end', '>', $currentDateTime)->orWhereNull('uug.date_end'))
                    ->whereIn(DB::raw('COALESCE(ug.context_id, 0)'), array_map(intval(...), $contextIds))
                    ->when(!is_null($roleIds), fn (Builder $q) => $q->whereIn('ug.role_id', $roleIds))
            )->pluck('user_id');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationSubscriptionSettingsDAO', '\NotificationSubscriptionSettingsDAO');
}
