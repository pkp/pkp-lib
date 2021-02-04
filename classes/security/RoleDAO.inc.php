<?php

/**
 * @file classes/security/RoleDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RoleDAO
 * @ingroup security
 *
 * @brief Operations for retrieving and modifying Role objects.
 */

import('lib.pkp.classes.security.Role');
import('lib.pkp.classes.security.UserGroupAssignment');

class RoleDAO extends DAO {
	/** @var The User DAO to return User objects when necessary **/
	var $userDao;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->userDao = DAORegistry::getDAO('UserDAO');
	}

	/**
	 * Create new data object
	 * @return Role
	 */
	function newDataObject() {
		return new Role();
	}

	/**
	 * Retrieve a list of users in a specified role.
	 * @param $roleId int optional (can leave as null to get all users in context)
	 * @param $contextId int optional, include users only in this context
	 * @param $searchType int optional, which field to search
	 * @param $search string optional, string to match
	 * @param $searchMatch string optional, type of match ('is' vs. 'contains' vs. 'startsWith')
	 * @param $dbResultRange object DBRangeInfo object describing range of results to return
	 * @return array matching Users
	 */
	function getUsersByRoleId($roleId = null, $contextId = null, $searchType = null, $search = null, $searchMatch = null, $dbResultRange = null) {
		$paramArray = array(ASSOC_TYPE_USER, 'interest', IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME);
		$paramArray = array_merge($paramArray, $this->userDao->getFetchParameters());
		if (isset($roleId)) $paramArray[] = (int) $roleId;
		if (isset($contextId)) $paramArray[] = (int) $contextId;
		// For security / resource usage reasons, a role or context ID
		// must be specified. Don't allow calls supplying neither.
		if ($contextId === null && $roleId === null) return null;

		$searchSql = '';

		$searchTypeMap = array(
			IDENTITY_SETTING_GIVENNAME => 'usgs.setting_value',
			IDENTITY_SETTING_FAMILYNAME => 'usfs.setting_value',
			USER_FIELD_USERNAME => 'u.username',
			USER_FIELD_EMAIL => 'u.email',
			USER_FIELD_INTERESTS => 'cves.setting_value'
		);

		if (!empty($search) && isset($searchTypeMap[$searchType])) {
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
		} elseif (!empty($search)) switch ($searchType) {
			case USER_FIELD_USERID:
				$searchSql = 'AND u.user_id=?';
				$paramArray[] = $search;
				break;
		}

		$searchSql .= ' ' . $this->userDao->getOrderBy(); // FIXME Add "sort field" parameter?

		$result = $this->retrieveRange(
			'SELECT DISTINCT u.*,
			' . $this->userDao->getFetchColumns() . '
			FROM users AS u
			LEFT JOIN user_user_groups uug ON (uug.user_id = u.user_id)
			LEFT JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id)
			LEFT JOIN controlled_vocabs cv ON (cv.assoc_type = ? AND cv.assoc_id = u.user_id AND cv.symbolic = ?)
			LEFT JOIN user_settings usgs ON (usgs.user_id = u.user_id AND usgs.setting_name = ?)
			LEFT JOIN user_settings usfs ON (usfs.user_id = u.user_id AND usfs.setting_name = ?)
			LEFT JOIN controlled_vocab_entries cve ON (cve.controlled_vocab_id = cv.controlled_vocab_id)
			LEFT JOIN controlled_vocab_entry_settings cves ON (cves.controlled_vocab_entry_id = cve.controlled_vocab_entry_id)
			' . $this->userDao->getFetchJoins() . '
			WHERE 1=1' . (isset($roleId) ? ' AND ug.role_id = ?' : '') . (isset($contextId) ? ' AND ug.context_id = ?' : '') . ' ' . $searchSql,
			$paramArray,
			$dbResultRange
		);

		return new DAOResultFactory($result, $this->userDao, '_returnUserFromRowWithData');
	}

	/**
	 * Validation check to see if a user belongs to any group that has a given role
	 * @param $contextId int
	 * @param $userId int
	 * @param $roleId int|array ROLE_ID_...
	 * @return bool True iff at least one such role exists
	 */
	function userHasRole($contextId, $userId, $roleId) {
		$roleId = is_array($roleId) ? join(',', array_map('intval', $roleId)) : (int) $roleId;
		$result = $this->retrieve(
			'SELECT count(*) AS row_count FROM user_groups ug JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE ug.context_id = ? AND uug.user_id = ? AND ug.role_id IN (' . $roleId . ')',
			[(int) $contextId, (int) $userId]
		);
		$row = (array) $result->current();
		return $row && $row['row_count'];
	}

	/**
	 * Return an array of row objects corresponding to the roles a given use has
	 * @param $userId
	 * @param $contextId
	 * @return array of Roles
	 */
	function getByUserId($userId, $contextId = null) {
		$params = array((int) $userId);
		if ($contextId !== null) $params[] = (int) $contextId;
		$result = $this->retrieve(
			'SELECT	DISTINCT ug.role_id AS role_id
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	uug.user_id = ?' . ($contextId !== null ? ' AND ug.context_id = ?' : ''),
			$params
		);

		$roles = [];
		foreach ($result as $row) {
			$role = $this->newDataObject();
			$role->setRoleId($row->role_id);
			$roles[] = $role;
		}
		return $roles;
	}

	/**
	 * Return an array of objects corresponding to the roles a given user has,
	 * grouped by context id.
	 * @param $userId int
	 * @return array
	 */
	function getByUserIdGroupedByContext($userId) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
		$userGroupsFactory = $userGroupDao->getByUserId($userId);

		$roles = array();
		while ($userGroup = $userGroupsFactory->next()) {
			$role = $roleDao->newDataObject();
			$role->setRoleId($userGroup->getRoleId());
			$roles[$userGroup->getContextId()][$userGroup->getRoleId()] = $role;
		}

		return $roles;
	}

	/**
	 * Get role forbidden stages.
	 * @param $roleId int Specific role ID to fetch stages for, if any
	 * @return array With $roleId, array(WORKFLOW_STAGE_ID_...); without,
	 *  array(ROLE_ID_... => array(WORKFLOW_STAGE_ID_...))
	 */
	function getForbiddenStages($roleId = null) {
		$forbiddenStages = array(
			ROLE_ID_MANAGER => array(
				// Journal managers should always have all stage selections locked by default.
				WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
			),
			ROLE_ID_REVIEWER => array(
				// Reviewer user groups should only have review stage assignments.
				WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
			),
			ROLE_ID_READER => array(
				// Reader user groups should have no stage assignments.
				WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
			),
		);

		if ($roleId) {
			if (isset($forbiddenStages[$roleId])) {
				return $forbiddenStages[$roleId];
			} else {
				return array();
			}
		} else {
			return $forbiddenStages;
		}
	}

	/**
	 *  All stages are always active for these permission levels.
	 * @return array array(ROLE_ID_MANAGER...);
	 */
	function getAlwaysActiveStages() {
		$alwaysActiveStages = array(ROLE_ID_MANAGER);
		return $alwaysActiveStages;
	}
}


