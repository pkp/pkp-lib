<?php

/**
 * @file classes/context/ContextDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextDAO
 * @ingroup core
 * @see DAO
 *
 * @brief Operations for retrieving and modifying context objects.
 */
import('lib.pkp.classes.db.SchemaDAO');

abstract class ContextDAO extends SchemaDAO {
	/**
	 * Retrieve the IDs and names of all contexts in an associative array.
	 * @param $enabledOnly true iff only enabled contexts are to be included
	 * @return array
	 */
	function getNames($enabledOnly = false) {
		$contexts = array();
		$iterator = $this->getAll($enabledOnly);
		while ($context = $iterator->next()) {
			$contexts[$context->getId()] = $context->getLocalizedName();
		}
		return $contexts;
	}

	/**
	 * Get a list of localized settings.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('name', 'description');
	}

	/**
	 * Check if a context exists with a specified path.
	 * @param $path string the path for the context
	 * @return boolean
	 */
	function existsByPath($path) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count FROM ' . $this->tableName . ' WHERE path = ?',
			[(string) $path]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
	}

	/**
	 * Retrieve a context by path.
	 * @param $path string
	 * @return Context?
	 */
	function getByPath($path) {
		$result = $this->retrieve(
			'SELECT * FROM ' . $this->tableName . ' WHERE path = ?',
			[(string) $path]
		);
		$row = (array) $result->current();
		return $row?$this->_fromRow($row):null;
	}

	/**
	 * Retrieve all contexts.
	 * @param $enabledOnly true iff only enabled contexts should be included
	 * @param $rangeInfo Object optional
	 * @return DAOResultFactory containing matching Contexts
	 */
	function getAll($enabledOnly = false, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT * FROM ' . $this->tableName .
			($enabledOnly?' WHERE enabled = 1':'') .
			' ORDER BY seq',
			[],
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve available contexts.
	 * If user-based contexts, retrieve all contexts assigned by user group
	 *   or all contexts for site admin
	 * If not user-based, retrieve all enabled contexts.
	 * @param $userId int Optional user ID to find available contexts for
	 * @param $rangeInfo Object optional
	 * @return DAOResultFactory containing matching Contexts
	 */
	function getAvailable($userId = null, $rangeInfo = null) {
		$params = [];
		if ($userId) $params = array_merge(
			$params,
			[(int) $userId, (int) $userId, (int) ROLE_ID_SITE_ADMIN]
		);

		$result = $this->retrieveRange(
			'SELECT c.* FROM ' . $this->tableName . ' c
			WHERE	' .
				($userId?
					'c.' . $this->primaryKeyColumn . ' IN (SELECT DISTINCT ug.context_id FROM user_groups ug JOIN user_user_groups uug ON (ug.user_group_id = uug.user_group_id) WHERE uug.user_id = ?)
					OR ? IN (SELECT user_id FROM user_groups ug JOIN user_user_groups uug ON (ug.user_group_id = uug.user_group_id) WHERE ug.role_id = ?)'
				:'c.enabled = 1') .
			' ORDER BY seq',
			$params,
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get journals by setting.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $contextId int
	 * @return DAOResultFactory
	 */
	function getBySetting($settingName, $settingValue, $contextId = null) {
		$params = array($settingName, $settingValue);
		if ($contextId) $params[] = $contextId;

		$result = $this->retrieve(
			'SELECT * FROM ' . $this->tableName . ' AS c
			LEFT JOIN ' . $this->settingsTableName . ' AS cs
			ON c.' . $this->primaryKeyColumn . ' = cs.' . $this->primaryKeyColumn .
			' WHERE cs.setting_name = ? AND cs.setting_value = ?' .
			($contextId?' AND c.' . $this->primaryKeyColumn . ' = ?':''),
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Sequentially renumber each context according to their sequence order.
	 */
	function resequence() {
		$result = $this->retrieve('SELECT ' . $this->primaryKeyColumn . ' AS context_id FROM ' . $this->tableName . ' ORDER BY seq');
		$i=1;
		for ($i=1; $row = (array) $result->current(); $i+=2 && $result->next()) {
			$this->update('UPDATE ' . $this->tableName . ' SET seq = ? WHERE ' . $this->primaryKeyColumn . ' = ?', [$i, $row['context_id']]);
			$result->next();
			$i+=2;
		}
	}
}
