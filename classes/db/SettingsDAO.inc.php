<?php

/**
 * @file classes/db/SettingsDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsDAO
 * @ingroup db
 * @deprecated
 *
 * @brief Operations for retrieving and modifying settings.
 */

abstract class SettingsDAO extends DAO {

	/**
	 * Retrieve all settings.
	 * @param $id int
	 * @return array Associative array of settings.
	 */
	function loadSettings($id) {
		$settings = array();

		$result = $this->retrieve(
			'SELECT setting_name, setting_value, setting_type, locale FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
			(int) $id
		);

		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$value = $this->convertFromDB($row['setting_value'], $row['setting_type']);
			if ($row['locale'] == '') $settings[$row['setting_name']] = $value;
			else $settings[$row['setting_name']][$row['locale']] = $value;
			$result->MoveNext();
		}
		$result->Close();

		return $settings;
	}

	/**
	 * Retrieve settings
	 * @param $id int
	 * @return array Associative array of settings.
	 */
	function &getSettings($id) {
		return $this->loadSettings($id);
	}

	/**
	 * Retrieve a setting value.
	 * @param $id int
	 * @param $name string
	 * @param $locale string optional
	 * @return mixed
	 */
	function &getSetting($id, $name, $locale = null) {
		$settings = $this->loadSettings($id);
		if (isset($settings[$name])) $returner = $settings[$name];
		else $returner = null;
		if ($locale !== null) {
			if (!isset($returner[$locale]) || !is_array($returner)) {
				unset($returner);
				$returner = null;
				return $returner;
			}
			return $returner[$locale];
		}
		return $returner;
	}

	/**
	 * Add/update a setting.
	 * @param $id int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $isLocalized boolean
	 */
	function updateSetting($id, $name, $value, $type = null, $isLocalized = false) {
		$keyFields = array('setting_name', 'locale', $this->_getPrimaryKeyColumn());
		if (!$isLocalized) {
			$value = $this->convertToDB($value, $type);
			$this->replace($this->_getTableName(),
				array(
					$this->_getPrimaryKeyColumn() => $id,
					'setting_name' => $name,
					'setting_value' => $value,
					'setting_type' => $type,
					'locale' => ''
				),
				$keyFields
			);
		} else {
			if (is_array($value)) foreach ($value as $locale => $localeValue) {
				$this->update('DELETE FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ? AND setting_name = ? AND locale = ?', array($id, $name, $locale));
				if (empty($localeValue)) continue;
				$type = null;
				$this->update('INSERT INTO ' . $this->_getTableName() . '
					(' . $this->_getPrimaryKeyColumn() . ', setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
					array(
						$id, $name, $this->convertToDB($localeValue, $type), $type, $locale
					)
				);
			}
		}
	}

	/**
	 * Delete a setting.
	 * @param $id int
	 * @param $name string
	 */
	function deleteSetting($id, $name, $locale = null) {
		$params = array($id, $name);
		$sql = 'DELETE FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ? AND setting_name = ?';
		if ($locale !== null) {
			$params[] = $locale;
			$sql .= ' AND locale = ?';
		}

		return $this->update($sql, $params);
	}

	/**
	 * Delete all settings for an ID.
	 * @param $id int
	 */
	function deleteById($id) {
		return $this->update(
			'DELETE FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
			(int) $id
		);
	}

	/**
	 * Get the settings table name.
	 * @return string
	 */
	abstract protected function _getTableName();

	/**
	 * Get the primary key column name.
	 * @return string
	 */
	abstract protected function _getPrimaryKeyColumn();
}

