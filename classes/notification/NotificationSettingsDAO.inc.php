<?php

/**
 * @file classes/notification/NotificationSettingsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationSettingsDAO
 * @ingroup notification
 *
 * @see Notification
 *
 * @brief Operations for retrieving and modifying Notification metadata.
 */

namespace PKP\notification;

use APP\notification\Notification;
use Illuminate\Support\Facades\DB;

class NotificationSettingsDAO extends \PKP\db\DAO
{
    /**
     * Update a notification's metadata
     *
     * @param int $notificationId
     *
     * @return $params array
     */
    public function getNotificationSettings($notificationId)
    {
        $result = $this->retrieve(
            'SELECT * FROM notification_settings WHERE notification_id = ?',
            [(int) $notificationId]
        );

        $params = [];
        foreach ($result as $row) {
            $name = $row->setting_name;
            $value = $this->convertFromDB($row->setting_value, $row->setting_type);
            $locale = $row->locale;

            if ($locale == '') {
                $params[$name] = $value;
            } else {
                $params[$name][$locale] = $value;
            }
        }
        return $params;
    }

    /**
     * Store a notification's metadata
     *
     * @param int $notificationId
     * @param string $name
     * @param string $value
     * @param bool $isLocalized optional
     * @param string $type optional
     */
    public function updateNotificationSetting($notificationId, $name, $value, $isLocalized = false, $type = null)
    {
        if (!$isLocalized) {
            $value = $this->convertToDB($value, $type);
            DB::table('notification_settings')->updateOrInsert(
                ['notification_id' => (int) $notificationId, 'setting_name' => $name, 'locale' => ''],
                ['setting_value' => $value, 'setting_type' => $type]
            );
        } else {
            if (is_array($value)) {
                foreach ($value as $locale => $localeValue) {
                    $this->update('DELETE FROM notification_settings WHERE notification_id = ? AND setting_name = ? AND locale = ?', [$notificationId, $name, $locale]);
                    if (empty($localeValue)) {
                        continue;
                    }
                    $type = null;
                    $this->update(
                        'INSERT INTO notification_settings
					(notification_id, setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
                        [
                            (int) $notificationId,
                            $name, $this->convertToDB($localeValue, $type),
                            $type,
                            $locale
                        ]
                    );
                }
            }
        }
    }

    /**
     * Delete all settings for a notification
     *
     * @param int $notificationId
     */
    public function deleteSettingsByNotificationId($notificationId)
    {
        return DB::table('notification_settings')
            ->where('notification_id', '=', $notificationId)
            ->delete();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\NotificationSettingsDAO', '\NotificationSettingsDAO');
}
