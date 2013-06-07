<?php
/**
 * @file classes/context/DefaultSettingDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DefaultSettingDAO
 * @ingroup context
 *
 * @brief Operations for retrieving and modifying context default settings.
 */

define('DEFAULT_SETTING_GENRES',		1);
define('DEFAULT_SETTING_PUBLICATION_FORMATS',	2);

class DefaultSettingDAO extends DAO {
	/**
	 * Constructor
	 */
	function DefaultSettingDAO() {
		parent::DAO();
	}

	/**
	 * Install setting types from an XML file.
	 * @param $contextId int
	 * @return boolean
	 */
	function installDefaultBase($contextId) {
		return null;
	}

	/**
	 * Get the path of the settings file.
	 * @return string
	 */
	function getDefaultBaseFilename() {
		return null;
	}

	/**
	 * Get the column name of the primary key
	 * @return string
	 */
	function getPrimaryKeyColumnName() {
		// Must be implemented by sub-classes.
		assert(false);
	}

	/**
	 * Get the column name of the constant key identifier.
	 * @return string
	 */
	function getDefaultKey() {
		return 'entry_key';
	}

	/**
	 * Get the names and values for setting attributes.
	 * In subclasses: if $node is null, return only the attribute names.
	 * @param $node XMLNode
	 * @param $onlyNames bool
	 * @return array key=>value
	 */
	function getSettingAttributes($node = null) {
		return array();
	}

	/**
	 * Get the name of the settings table.
	 * @return string
	 */
	function getSettingsTableName() {
		return null;
	}

	/**
	 * Get the name of the main table for this setting group.
	 * @return string
	 */
	function getTableName() {
		return null;
	}

	/**
	 * Get the default type constant.
	 * @return int
	 */
	function getDefaultType() {
		return null;
	}

	/**
	 * Install setting type localized data from an XML file.
	 * @param $locale string
	 * @param $contextId int
	 * @param $skipLoad bool
	 * @param $localInstall bool
	 * @return boolean
	 */
	function installDefaultBaseData($locale, $contextId, $skipLoad = true, $localeInstall = false) {
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parse($this->getDefaultBaseFilename());
		if (!$data) return false;
		$defaultIds = $this->getDefaultSettingIds($contextId);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_PKP_DEFAULT, $locale);

		foreach ($data->getChildren() as $formatNode) {

			$settings =& $this->getSettingAttributes($formatNode, $locale);

			if (empty($defaultIds[$formatNode->getAttribute('key')])) { // ignore keys not associated with this context
				continue;
			} else { // prepare a list of attributes not defined in the current settings xml file
				unset($defaultIds[$formatNode->getAttribute('key')]);
			}

			foreach ($settings as $settingName => $settingValue) {

				$this->update(
					'INSERT INTO context_defaults
					(context_id, assoc_type, entry_key, locale, setting_name, setting_value, setting_type)
					VALUES
					(?, ?, ?, ?, ?, ?, ?)',
					array(
						$contextId,
						$this->getDefaultType(),
						$formatNode->getAttribute('key'),
						$locale,
						$settingName,
						$settingValue,
						'string'
					)
				);
			}
		}

		$attributeNames =& $this->getSettingAttributes();

		// install defaults for keys not defined in the xml
		foreach ($defaultIds as $key => $id) {
			foreach ($attributeNames as $setting) {
				$this->update(
					'INSERT INTO context_defaults
					(context_id, assoc_type, entry_key, locale, setting_name, setting_value, setting_type)
					VALUES
					(?, ?, ?, ?, ?, ?, ?)',
					array(
						$contextId,
						$this->getDefaultType(),
						$key,
						$locale,
						$setting,
						'##',
						'string'
					)
				);
			}
		}

		if ($skipLoad) {
			return true;
		}

		if ($localeInstall) {
			$this->restoreByContextId($contextId, $locale);
		} else {
			$this->restoreByContextId($contextId);
		}

		return true;
	}

	/**
	 * Retrieve ids for all default setting entries
	 * @param $contextId int
	 */
	function &getDefaultSettingIds($contextId) {
		$result = $this->retrieve(
			'SELECT '. $this->getPrimaryKeyColumnName() .', '. $this->getDefaultKey() .' FROM '. $this->getTableName() .'
			WHERE context_id = ? AND '. $this->getDefaultKey() .' IS NOT NULL', $contextId
		);

		$returner = null;
		while (!$result->EOF) {
			$returner[$result->fields[$this->getDefaultKey()]] =& $result->fields[$this->getPrimaryKeyColumnName()];
			$result->MoveNext();
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Restore settings.
	 * @param $contextId int
	 * @param $locale string
	 */
	function restoreByContextId($contextId, $locale = null) {

		$defaultIds = $this->getDefaultSettingIds($contextId);

		if ($locale) {
			foreach ($defaultIds as $key => $id) {
				$this->update('DELETE FROM '. $this->getSettingsTableName() .' WHERE '. $this->getPrimaryKeyColumnName() .' = ? AND locale = ?', array($id, $locale));
			}
		} else {
			foreach ($defaultIds as $key => $id) {
				$this->update('DELETE FROM '. $this->getSettingsTableName() .' WHERE '. $this->getPrimaryKeyColumnName() .' = ?', $id);
			}
		}

		if (!$locale) {
			$this->update('UPDATE '. $this->getTableName() .' SET enabled = ? WHERE context_id = ? AND '. $this->getDefaultKey() .' IS NOT NULL', array(1, $contextId));
			$this->update('UPDATE '. $this->getTableName() .' SET enabled = ? WHERE context_id = ? AND '. $this->getDefaultKey() .' IS NULL', array(0, $contextId));
		}

		$sql = 'SELECT * FROM context_defaults WHERE context_id = ? AND assoc_type = ?';
		$sqlParams = array($contextId, $this->getDefaultType());
		if ($locale) {
			$sql .= ' AND locale = ?';
			$sqlParams[] = $locale;
		}

		$result = $this->retrieve($sql, $sqlParams);

		$returner = null;
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$this->update(
				'INSERT INTO '. $this->getSettingsTableName() .'
				('. $this->getPrimaryKeyColumnName() .', locale, setting_name, setting_value, setting_type)
				VALUES
				(?, ?, ?, ?, ?)',
				array($defaultIds[$row['entry_key']], $row['locale'], $row['setting_name'], $row['setting_value'], $row['setting_type'])
			);
			$result->MoveNext();
		}
		$result->Close();
	}

	/**
	 * Install default data for settings.
	 * @param $contextId int
	 * @param $locales array
	 */
	function installDefaults($contextId, $locales) {
		$this->installDefaultBase($contextId);
		foreach ($locales as $locale) {
			$this->installDefaultBaseData($locale, $contextId);
		}
		$this->restoreByContextId($contextId);
	}

	/**
	 * Install locale specific items for a locale.
	 * @param $locale string
	 */
	function installLocale($locale) {
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getNames();

		foreach ($contexts as $id => $name) {
			$this->installDefaultBaseData($locale, $id, false, true);
		}

	}

	/**
	 * Delete locale specific items from the settings table.
	 * @param $locale string
	 */
	function uninstallLocale($locale) {
		$this->update('DELETE FROM '. $this->getSettingsTableName() .' WHERE locale = ?', array($locale));
		$this->update('DELETE FROM context_defaults WHERE locale = ?', array($locale));
	}
}

?>
