<?php

/**
 * @file classes/security/PKPUserGroupDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserGroupDAO
 * @ingroup security
 * @see UserGroup
 *
 * @brief Operations for retrieving and modifying User Groups and user group assignments
 * FIXME: Some of the context-specific features of this class will have
 * to be changed for zero- or double-context applications when user groups
 * are ported over to them.
 */


import('lib.pkp.classes.security.UserGroup');

class PKPUserGroupDAO extends DAO {
	/** @var a shortcut to get the UserDAO **/
	var $userDao;

	/** @var a shortcut to get the UserGroupAssignmentDAO **/
	var $userGroupAssignmentDao;

	/**
	 * Constructor.
	 */
	function PKPUserGroupDAO() {
		parent::DAO();
		$this->userDao =& DAORegistry::getDAO('UserDAO');
		$this->userGroupAssignmentDao =& DAORegistry::getDAO('UserGroupAssignmentDAO');
	}

	/**
	 * create new data object
	 * (allows DAO to be subclassed)
	 */
	function &newDataObject() {
		$dataObject = new UserGroup();
		return $dataObject;
	}

	/**
	 * Internal function to return a UserGroup object from a row.
	 * @param $row array
	 * @return PKPUserGroup
	 */
	function &_returnFromRow(&$row) {
		$userGroup =& $this->newDataObject();
		$userGroup->setId($row['user_group_id']);
		$userGroup->setRoleId($row['role_id']);
		$userGroup->setContextId($row['context_id']);
		$userGroup->setPath($row['path']);
		$userGroup->setDefault($row['is_default']);

		$this->getDataObjectSettings('user_group_settings', 'user_group_id', $row['user_group_id'], $userGroup);

		HookRegistry::call('PKPUserGroupDAO::_returnFromRow', array(&$userGroup, &$row));

		return $userGroup;
	}

	/**
	 * Insert a user group.
	 * @param $userGroup UserGroup
	 */
	function insertUserGroup(&$userGroup) {
		$returner = $this->update(
			'INSERT INTO user_groups
				(role_id, path, context_id, is_default)
				VALUES
				(?, ?, ?, ?)',
			array(
				(int) $userGroup->getRoleId(),
				$userGroup->getPath(),
				(int) $userGroup->getContextId(),
				($userGroup->getDefault()?1:0)
			)
		);

		$userGroup->setId($this->getInsertUserGroupId());
		$this->updateLocaleFields($userGroup);
		return $this->getInsertUserGroupId();
	}

	/**
	 * Delete a user group by its id
	 * will also delete related settings and all the assignments to this group
	 * @param $contextId int
	 * @param $userGroupId int
	 */
	function deleteById($contextId, $userGroupId) {
		$ret1 = $this->userGroupAssignmentDao->deleteAssignmentsByUserGroupId($userGroupId);
		$ret2 = $this->update('DELETE FROM user_group_settings WHERE user_group_id = ?', (int) $userGroupId);
		$ret3 = $this->update('DELETE FROM user_groups WHERE user_group_id = ?', (int) $userGroupId);
		$ret4 = $this->removeAllStagesFromGroup($contextId, $userGroupId);
		return $ret1 && $ret2 && $ret3 && $ret4;
	}

	/**
	 * Delete a user group.
	 * will also delete related settings and all the assignments to this group
	 * @param $userGroup UserGroup
	 */
	function deleteUserGroup(&$userGroup) {
		return $this->deleteById($userGroup->getContextId(), $userGroup->getId());
	}


	/**
	 * Delete a user group by its context id
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$result =& $this->retrieve('SELECT user_group_id FROM user_groups WHERE context_id = ?', (int) $contextId);

		$returner = true;
		for ($i=1; !$result->EOF; $i++) {
			list($userGroupId) = $result->fields;

			$ret1 = $this->update('DELETE FROM user_group_stage WHERE user_group_id = ?', (int) $userGroupId);
			$ret2 = $this->update('DELETE FROM user_group_settings WHERE user_group_id = ?', (int) $userGroupId);
			$ret3 = $this->update('DELETE FROM user_groups WHERE user_group_id = ?', (int) $userGroupId);

			$returner = $returner && $ret1 && $ret2 && $ret3;
			$result->MoveNext();
		}

		return $returner;
	}

	/**
	 * Get the ID of the last inserted user group.
	 * @return int
	 */
	function getInsertUserGroupId() {
		return $this->getInsertId('user_groups', 'user_group_id');
	}

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return parent::getLocaleFieldNames() + array('name', 'abbrev');
	}

	/**
	 * Update the localized data for this object
	 * @param $author object
	 */
	function updateLocaleFields(&$userGroup) {
		$this->updateDataObjectSettings('user_group_settings', $userGroup, array(
			'user_group_id' => (int) $userGroup->getId()
		));
	}

	/**
	 * Get an individual user group
	 * @param $userGroupId
	 * @param $contextId
	 */
	function getById($userGroupId, $contextId = null) {
		$params = array((int) $userGroupId);
		if ($contextId !== null) $params[] = (int) $contextId;
		$result =& $this->retrieve(
			'SELECT	user_group_id, context_id, role_id, path, is_default
			FROM	user_groups
			WHERE	user_group_id = ?' . ($contextId !== null?' AND context_id = ?':''),
			$params
		);

		return $this->_returnFromRow($result->GetRowAssoc(false));
	}

	/**
	 * Get a single default user group with a particular roleId
	 * FIXME: ??
	 * @param $contextId
	 * @param $roleId
	 */
	function &getDefaultByRoleId($contextId, $roleId) {
		$returner = false;
		$allDefaults =& $this->getByRoleId($contextId, $roleId, true);
		if ( $allDefaults->eof() ) return $returner;
		$returner =& $allDefaults->next();
		return $returner;
	}

	/**
	 * Get all user groups belonging to a role
	 * @param $contextId
	 * @param $roleId
	 * @param $default
	 * @return DAOResultFactory
	 */
	function &getByRoleId($contextId, $roleId, $default = false) {
		$params = array((int) $contextId, (int) $roleId);
		if ($default) $params[] = 1; // true
		$result =& $this->retrieve(
			'SELECT	*
			FROM	user_groups
			WHERE	context_id = ? AND
				role_id = ?' . ($default?' AND is_default = ?':''),
			$params
		);

		$returner = new DAOResultFactory($result, $this, '_returnFromRow');
		return $returner;
	}

	/**
	 * Get an array of user group ids belonging to a given role
	 * @param $roleId in
	 * @param $contextId int
	 */
	function &getUserGroupIdsByRoleId($roleId, $contextId = null) {
		$sql = 'SELECT user_group_id FROM user_groups WHERE role_id = ?';
		$params = array((int) $roleId);

		if ($contextId) {
			$sql .= ' AND context_id = ?';
			$params[] = (int) $contextId;
		}

		$result =& $this->retrieve($sql, $params);

		$userGroupIds = array();
		while (!$result->EOF) {
			$userGroupIds[] = (int) $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $userGroupIds;
	}

	/**
	 * Check if a user is in a particular user group
	 * @param $contextId int
	 * @param $userId int
	 * @param $userGroupId int
	 * @return boolean
	 */
	function userInGroup($userId, $userGroupId) {
		$result =& $this->retrieve(
			'SELECT	count(*)
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE
				uug.user_id = ? AND
				ug.user_group_id = ?',
			array((int) $userId, (int) $userGroupId)
		);

		// > 0 because user could belong to more than one user group with this role
		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Check if a user is in any user group
	 * @param $userId int
	 * @param $contextId int optional
	 * @return boolean
	 */
	function userInAnyGroup($userId, $contextId = null) {
		$params = array((int) $userId);
		if ($contextId) $params[] = (int) $contextId;

		$result =& $this->retrieve(
			'SELECT	count(*)
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	uug.user_id = ?' . ($contextId?' AND ug.context_id = ?':''),
			$params
		);

		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Retrieve user groups to which a user is assigned.
	 * @param $userId int
	 * @param $contextId int
	 * @return DAOResultFactory
	 */
	function &getByUserId($userId, $contextId = null){
		$params = array((int) $userId);
		if ($contextId) {
			$params[] = (int) $contextId;
		}
		$result =& $this->retrieve(
			'SELECT	ug.*
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
				WHERE uug.user_id = ?' . ($contextId?' AND ug.context_id = ?':''),
			$params
		);

		$returner = new DAOResultFactory($result, $this, '_returnFromRow');
		return $returner;
	}

	/**
	 * Validation check to see if user group exists for a given context
	 * @param $contextId
	 * @param $userGroupId
	 * @return bool
	 */
	function contextHasGroup($contextId, $userGroupId) {
		$result =& $this->retrieve(
			'SELECT count(*)
				FROM user_groups ug
				WHERE ug.user_group_id = ?
				AND ug.context_id = ?',
			array (
				(int) $userGroupId,
				(int) $contextId
			)
		);

		$returner = isset($result->fields[0]) && $result->fields[0] == 0 ? false : true;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Retrieve user groups for a given context (all contexts if null)
	 * @param $contextId
	 */
	function &getByContextId($contextId = null) {
		$params = array();
		if ($contextId) $params[] = (int) $contextId;
		$result =& $this->retrieve(
			'SELECT ug.*
			FROM	user_groups ug' .
				($contextId?' WHERE ug.context_id = ?':''),
			$params);

		$returner = new DAOResultFactory($result, $this, '_returnFromRow');
		return $returner;
	}

	/**
	 * Retrieve the number of users associated with the specified context.
	 * @param $contextId int
	 * @return int
	 */
	function getContextUsersCount($contextId, $userGroupId = null, $roleId = null) {
		$params = array((int) $contextId);
		if ($userGroupId) $params[] = (int) $userGroupId;
		if ($roleId) $params[] = (int) $roleId;
		$result =& $this->retrieve(
			'SELECT	COUNT(DISTINCT(uug.user_id))
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	context_id = ?' . ($userGroupId?' AND ug.user_group_id = ?':'') . ($roleId?' AND ug.role_id = ?':''),
			$params
		);

		$returner = $result->fields[0];

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * return an Iterator of User objects given the search parameters
	 * @param int $contextId
	 * @param string $searchType
	 * @param string $search
	 * @param string $searchMatch
	 * @param DBResultRange $dbResultRange
	 */
	function &getUsersByContextId($contextId = null, $searchType = null, $search = null, $searchMatch = null, $dbResultRange = null) {
		return $this->getUsersById(null, $contextId, $searchType, $search, $searchMatch, $dbResultRange);
	}

	/**
	 * Find users that don't have a given role
	 * @param $contextId int optional
	 * @param ROLE_ID_... int (const)
	 * @param $search string
	 */
	function &getUsersNotInRole($roleId, $contextId = null, $search = null) {
		$params = array((int) $roleId);
		if ($contextId) $params[] = (int) $contextId;
		if(isset($search)) $params = array_merge($params, array_pad(array(), 5, '%' . $search . '%'));

		$result =& $this->retrieve(
			'SELECT DISTINCT u.*
			FROM	users u, user_groups ug, user_user_groups uug
			WHERE	ug.user_group_id = uug.user_group_id AND
				u.user_id = uug.user_id AND
				ug.role_id <> ?' .
				($contextId?' AND ug.context_id = ?':'') .
				(isset($search) ? ' AND (u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)':''),
			$params
		);

		$returner = new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData');
		return $returner;
	}

	/**
	 * return an Iterator of User objects given the search parameters
	 * @param int $userGroupId
	 * @param int $contextId
	 * @param string $searchType
	 * @param string $search
	 * @param string $searchMatch
	 * @param DBResultRange $dbResultRange
	 */
	function &getUsersById($userGroupId = null, $contextId = null, $searchType = null, $search = null, $searchMatch = null, $dbResultRange = null) {
		$paramArray = array();

		if (isset($userGroupId)) $paramArray[] = (int) $userGroupId;
		if (isset($contextId)) $paramArray[] = (int) $contextId;

		// For security / resource usage reasons, a user group or context ID
		// must be specified. Don't allow calls supplying neither.
		if ($contextId === null && $userGroupId === null) return null;

		$searchSql = $this->_getSearchSql($searchType, $search, $searchMatch, $paramArray);

		$sql = 'SELECT DISTINCT u.*
			FROM users AS u
			LEFT JOIN user_settings us ON (us.user_id = u.user_id AND us.setting_name = "affiliation")
			LEFT JOIN user_interests ui ON (u.user_id = ui.user_id)
			LEFT JOIN controlled_vocab_entry_settings cves ON (ui.controlled_vocab_entry_id = cves.controlled_vocab_entry_id)
			LEFT JOIN user_user_groups uug ON (uug.user_id = u.user_id)
			LEFT JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id) WHERE';


		$sql .= (isset($userGroupId) ? ' ug.user_group_id = ? ' . (isset($contextId) ? 'AND ' : '') : ' ');
		$sql .= (isset($contextId) ? ' ug.context_id = ? ' : ' ') . $searchSql;

		$result =& $this->retrieveRange(
			$sql,
			$paramArray,
			$dbResultRange
		);

		$returner = new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData');
		return $returner;
	}

	/**
	 * Retrieve those users with no group assignments in any press.
	 * @param array $filter an array of search critera
	 * @param boolean $allowDisabled
	 * @param DBResultRarnge $dbResultRange
	 * @return DAOResultFactory
	 */
	function &getUsersWithNoUserGroupAssignments($filter = null, $allowDisabled = true, $dbResultRange = null) {

		$sql = 'SELECT DISTINCT u.*
			FROM users AS u
			LEFT JOIN user_settings us ON (us.user_id = u.user_id AND us.setting_name = "affiliation")
			LEFT JOIN user_interests ui ON (u.user_id = ui.user_id)
			LEFT JOIN controlled_vocab_entry_settings cves ON (ui.controlled_vocab_entry_id = cves.controlled_vocab_entry_id)
			LEFT JOIN user_user_groups uug ON u.user_id=uug.user_id WHERE uug.user_group_id IS NULL ';

		$sql .= ($allowDisabled?'':' AND u.disabled = 0');

		$searchSql = '';
		$paramArray = array();

		if (isset($filter)) {
			$searchType = isset($filter['searchType']) ? $filter['searchType'] : null;
			$search = isset($filter['search']) ? $filter['search'] : null;
			$searchMatch = isset($filter['searchMatch']) ? $filter['searchMatch'] : null;

			$searchSql = $this->_getSearchSql($searchType, $search, $searchMatch, $paramArray);
			$sql .= $searchSql;
		}

		$result =& $this->retrieveRange($sql, $paramArray, $dbResultRange);
		$returner = new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData');
		return $returner;
	}

	//
	// UserGroupAssignment related
	//
	/**
	 * Delete all user group assignments for a given userId
	 * @param int $userId
	 */
	function deleteAssignmentsByUserId($userId, $userGroupId = null) {
		$this->userGroupAssignmentDao->deleteByUserId($userId, $userGroupId);
	}

	/**
	 * Delete all assignments to a given user group
	 * @param unknown_type $userGroupId
	 */
	function deleteAssignmentsByUserGroupId($userGroupId) {
		$this->userGroupAssignmentDao->deleteAssignmentsByUserGroupId($userGroupId);
	}

	/**
	 * Remove all user group assignments for a given user in a context
	 * @param int $contextId
	 * @param int $userId
	 */
	function deleteAssignmentsByContextId($contextId, $userId = null) {
		$this->userGroupAssignmentDao->deleteAssignmentsByContextId($contextId, $userId);
	}

	/**
	 * Assign a given user to a given user group
	 * @param int $userId
	 * @param int $groupId
	 */
	function assignUserToGroup($userId, $groupId) {
		$assignment =& $this->userGroupAssignmentDao->newDataObject();
		$assignment->setUserId($userId);
		$assignment->setUserGroupId($groupId);
		return $this->userGroupAssignmentDao->insertAssignment($assignment);
	}

	/**
	 * remove a given user from a given user group
	 * @param $userId int
	 * @param $groupId int
	 * @param $contextId int
	 */
	function removeUserFromGroup($userId, $groupId, $contextId) {
		$assignments =& $this->userGroupAssignmentDao->getByUserId($userId, $contextId);
		while ($assignment =& $assignments->next()) {
			if ($assignment->getUserGroupId() == $groupId) {
				$this->userGroupAssignmentDao->deleteAssignment($assignment);
			}
			unset($assignment);
		}
	}

	/**
	 * Delete all stage assignments in a user group.
	 * @param $contextId int
	 * @param $userGroupId int
	 */
	function removeAllStagesFromGroup($contextId, $userGroupId) {
		$assignedStages = $this->getAssignedStagesByUserGroupId($contextId, $userGroupId);
		foreach($assignedStages as $stageId => $stageLocaleKey) {
			$this->removeGroupFromStage($contextId, $userGroupId, $stageId);
		}
	}


	//
	// Extra settings (not handled by rest of Dao)
	//
	/**
	 * Method for updatea userGroup setting
	 * @param $userGroupId int
	 * @param $name string
	 * @param $value mixed
	 * @param $type string data type of the setting. If omitted, type will be guessed
	 * @param $isLocalized boolean
	 */
	function updateSetting($userGroupId, $name, $value, $type = null, $isLocalized = false) {
		$keyFields = array('setting_name', 'locale', 'user_group_id');

		if (!$isLocalized) {
			$value = $this->convertToDB($value, $type);
			$this->replace('user_group_settings',
				array(
					'user_group_id' => (int) $userGroupId,
					'setting_name' => $name,
					'setting_value' => $value,
					'setting_type' => $type,
					'locale' => ''
				),
				$keyFields
			);
		} else {
			if (is_array($value)) foreach ($value as $locale => $localeValue) {
				$this->update('DELETE FROM user_group_settings WHERE user_group_id = ? AND setting_name = ? AND locale = ?', array((int) $userGroupId, $name, $locale));
				if (empty($localeValue)) continue;
				$type = null;
				$this->update('INSERT INTO user_group_settings
					(user_group_id, setting_name, setting_value, setting_type, locale)
					VALUES (?, ?, ?, ?, ?)',
					array(
						$userGroupId, $name, $this->convertToDB($localeValue, $type), $type, $locale
					)
				);
			}
		}
	}


	/**
	 * Retrieve a context setting value.
	 * @param $userGroupId int
	 * @param $name string
	 * @param $locale string optional
	 * @return mixed
	 */
	function &getSetting($userGroupId, $name, $locale = null) {
		$params = array((int) $userGroupId, $name);
		if ($locale) $params[] = $locale;
		$result =& $this->retrieve(
			'SELECT	setting_name, setting_value, setting_type, locale
			FROM	user_group_settings
			WHERE	user_group_id = ? AND
				setting_name = ?' .
				($locale?' AND locale = ?':''),
			$params
		);

		$recordCount = $result->RecordCount();
		$returner = false;
		if ($recordCount == 1) {
			$row =& $result->getRowAssoc(false);
			$returner =& $this->convertFromDB($row['setting_value'], $row['setting_type']);
		} elseif ($recordCount > 1) {
			$returner = array();
			while (!$result->EOF) {
				$returner[$row['locale']] = $this->convertFromDB($row['setting_value'], $row['setting_type']);
				$result->MoveNext();
			}
			$result->Close();
			unset($result);
		}
		return $returner;
	}

	//
	// Install/Defaults with settings
	//

	/**
	 * Load the XML file and move the settings to the DB
	 * @param $contextId
	 * @param $filename
	 */
	function installSettings($contextId, $filename) {
		$xmlParser = new XMLParser();
		$tree = $xmlParser->parse($filename);

		if (!$tree) {
			$xmlParser->destroy();
			return false;
		}

		foreach ($tree->getChildren() as $setting) {
			$roleId = hexdec($setting->getAttribute('roleId'));
			$nameKey = $setting->getAttribute('name');
			$abbrevKey = $setting->getAttribute('abbrev');
			$defaultStages = explode(",", $setting->getAttribute('stages'));
			$userGroup =& $this->newDataObject();

			// create a role associated with this user group
			$role = new Role($roleId);
			$userGroup =& $this->newDataObject();
			$userGroup->setRoleId($roleId);
			$userGroup->setPath($role->getPath());
			$userGroup->setContextId($contextId);
			$userGroup->setDefault(true);

			// insert the group into the DB
			$userGroupId = $this->insertUserGroup($userGroup);

			// Install default groups for each stage
			foreach ($defaultStages as $stageId) {
				if (!empty($stageId) && $stageId <= WORKFLOW_STAGE_ID_PRODUCTION && $stageId >= WORKFLOW_STAGE_ID_SUBMISSION) {
					$this->assignGroupToStage($contextId, $userGroupId, $stageId);
				}
			}

			// add the i18n keys to the settings table so that they
			// can be used when a new locale is added/reloaded
			$this->updateSetting($userGroup->getId(), 'nameLocaleKey', $nameKey);
			$this->updateSetting($userGroup->getId(), 'abbrevLocaleKey', $abbrevKey);

			// install the settings in the current locale for this context
			$this->installLocale(AppLocale::getLocale(), $contextId);
		}
	}

	/**
	 * use the locale keys stored in the settings table to install the locale settings
	 * @param $locale
	 * @param $contextId
	 */
	function installLocale($locale, $contextId = null) {
		$userGroups =& $this->getByContextId($contextId);
		while (!$userGroups->eof()) {
			$userGroup =& $userGroups->next();
			$nameKey = $this->getSetting($userGroup->getId(), 'nameLocaleKey');
			$this->updateSetting($userGroup->getId(),
				'name',
				array($locale => __($nameKey, null, $locale)),
				'string',
				$locale,
				true
			);

			$abbrevKey = $this->getSetting($userGroup->getId(), 'abbrevLocaleKey');
			$this->updateSetting($userGroup->getId(),
				'abbrev',
				array($locale => __($abbrevKey, null, $locale)),
				'string',
				$locale,
				true
			);
			unset($userGroup);
		}
	}

	/**
	 * Remove all settings associated with a locale
	 * @param $locale
	 */
	function deleteSettingsByLocale($locale) {
		$result = $this->update('DELETE FROM user_group_settings WHERE locale = ?', $locale);
		return $result;
	}

	/**
	 * private function to assemble the SQL for searching users.
	 * @param string $searchType the field to search on.
	 * @param string $search the keywords to search for.
	 * @param string $searchMatch where to match (is, contains, startsWith).
	 * @param array $paramArray SQL parameter array reference
	 */
	function _getSearchSql($searchType, $search, $searchMatch, &$paramArray) {

		$searchTypeMap = array(
				USER_FIELD_FIRSTNAME => 'u.first_name',
				USER_FIELD_LASTNAME => 'u.last_name',
				USER_FIELD_USERNAME => 'u.username',
				USER_FIELD_EMAIL => 'u.email',
				USER_FIELD_AFFILIATION => 'us.setting_value'
		);

		$searchSql = '';

		if (!empty($search)) {

			if (!isset($searchTypeMap[$searchType])) {
				$concatFields = ' ( LOWER(CONCAT(' . join(', ', $searchTypeMap) . ')) LIKE ? OR LOWER(cves.setting_value) LIKE ? ) ';

				$search = strtolower($search);

				$words = preg_split('{\s+}', $search);
				$searchFieldMap = array();

				foreach ($words as $word) {
					$searchFieldMap[] = $concatFields;
					$term = '%' . $word . '%';
					array_push($paramArray, $term, $term);
				}

				$searchSql .= ' AND (  ' . join(' AND ', $searchFieldMap) . '  ) ';
			} else {
				$fieldName = $searchTypeMap[$searchType];
				switch ($searchMatch) {
					case 'is':
						$searchSql = "AND LOWER($fieldName) = LOWER(?)";
						$paramArray[] = $search;
						break;
					case 'contains':
						$searchSql = "AND LOWER($fieldName) LIKE LOWER(?)";
						$paramArray[] = '%' . $search . '%';
						break;
					case 'startsWith':
						$searchSql = "AND LOWER($fieldName) LIKE LOWER(?)";
						$paramArray[] = $search . '%';
						break;
				}
			}
		} else {
			switch ($searchType) {
				case USER_FIELD_USERID:
					$searchSql = 'AND u.user_id = ?';
					break;
				case USER_FIELD_INITIAL:
					$searchSql = 'AND LOWER(u.last_name) LIKE LOWER(?)';
					break;
			}
		}

		$searchSql .= ' ORDER BY u.last_name, u.first_name'; // FIXME Add "sort field" parameter?

		return $searchSql;
	}
}

?>
