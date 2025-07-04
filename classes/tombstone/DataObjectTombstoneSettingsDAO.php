<?php

/**
 * @file classes/tombstone/DataObjectTombstoneSettingsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTombstoneSettingsDAO
 *
 * @ingroup submission
 *
 * @brief Operations for retrieving and modifying submission tombstone settings.
 */

namespace PKP\tombstone;

use Illuminate\Support\Facades\DB;

class DataObjectTombstoneSettingsDAO extends \PKP\db\DAO
{
    /**
     * Retrieve an submission tombstone setting value.
     *
     * @param int $tombstoneId
     * @param string $name
     * @param string $locale optional
     */
    public function getSetting($tombstoneId, $name, $locale = null)
    {
        $params = [(int) $tombstoneId, $name];
        if ($locale !== null) {
            $params[] = $locale;
        }
        $result = $this->retrieve(
            'SELECT setting_value, setting_type, locale FROM data_object_tombstone_settings WHERE tombstone_id = ? AND setting_name = ?'
            . ($locale !== null ? ' AND locale = ?' : ''),
            $params
        );

        $setting = null;
        foreach ($result as $row) {
            $value = $this->convertFromDB($row->setting_value, $row->setting_type);
            if (!isset($row->locale) || $row->locale == '') {
                $setting[$name] = $value;
            } else {
                $setting[$name][$row->locale] = $value;
            }
        }
        return $setting;
    }

    /**
     * Add/update an submission tombstone setting.
     *
     * @param int $tombstoneId
     * @param string $name
     * @param string $type data type of the setting. If omitted, type will be guessed
     * @param bool $isLocalized
     */
    public function updateSetting($tombstoneId, $name, $value, $type = null, $isLocalized = false)
    {
        if (!$isLocalized) {
            $value = $this->convertToDB($value, $type);
            DB::table('data_object_tombstone_settings')->updateOrInsert(
                ['tombstone_id' => $tombstoneId, 'setting_name' => $name, 'locale' => ''],
                ['setting_value' => $value, 'setting_type' => $type]
            );
        }
        if (is_array($value)) {
            foreach ($value as $locale => $localeValue) {
                $this->update(
                    'DELETE FROM data_object_tombstone_settings WHERE tombstone_id = ? AND setting_name = ? AND locale = ?',
                    [(int) $tombstoneId, $name, $locale]
                );
                if (empty($localeValue)) {
                    continue;
                }
                $type = null;
                $this->update(
                    'INSERT INTO data_object_tombstone_settings
				(tombstone_id, setting_name, setting_value, setting_type, locale)
				VALUES (?, ?, ?, ?, ?)',
                    [(int) $tombstoneId, $name, $this->convertToDB($localeValue, $type), $type, $locale]
                );
            }
        }
    }

    /**
     * Delete an submission tombstone setting.
     *
     * @param int $tombstoneId
     * @param string $name
     * @param string $locale optional
     *
     * @return int Affected row count
     */
    public function deleteSetting($tombstoneId, $name, $locale = null)
    {
        $params = [(int) $tombstoneId, $name];
        $sql = 'DELETE FROM data_object_tombstone_settings WHERE tombstone_id = ? AND setting_name = ?';
        if ($locale !== null) {
            $params[] = $locale;
            $sql .= ' AND locale = ?';
        }
        return $this->update($sql, $params);
    }

    /**
     * Delete all settings for an submission tombstone.
     *
     * @param int $tombstoneId
     *
     * @return int Affected row count
     */
    public function deleteSettings($tombstoneId)
    {
        return $this->update(
            'DELETE FROM data_object_tombstone_settings WHERE tombstone_id = ?',
            [(int) $tombstoneId]
        );
    }
}
