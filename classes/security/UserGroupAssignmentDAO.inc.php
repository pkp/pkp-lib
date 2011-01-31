<?php

/**
 * @file classes/security/UserGroupAssignmentDAO.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroupAssignmentDAO.inc.php
 * @ingroup security
 * @see UserGroupAssigment
 *
 * @brief Operations for retrieving and modifying user group assignments
 */

import('lib.pkp.classes.security.UserGroupAssignment');

class UserGroupAssignmentDAO extends DAO {
	/**
	 * Constructor.
	 */
	function UserGroupAssignmentDAO() {
		parent::DAO();
	}

	/**
	 * Create a new UserGroupAssignment object
	 * (allows extensibility)
	 */
	function &newDataObject() {
		$dataObject = new UserGroupAssignment();
		return $dataObject;
	}

	/**
	 * Internal function to return a UserGroupAssignment object from a row.
	 * @param $row array
	 * @return Role
	 */
	function &_returnFromRow(&$row) {
		$userGroupAssignment =& $this->newDataObject();
		$userGroupAssignment->setUserGroupId($row['user_group_id']);
		$userGroupAssignment->setUserId($row['user_id']);
		return $userGroupAssignment;
	}

	/**
	 * Delete all user group assignments for a given userId
	 * @param int $userId
	 */
	function deleteByUserId($userId, $userGroupId = null) {
		$params = array($userId);
		if ($userGroupId) $params[] = $userGroupId;
		return $this->update('DELETE FROM user_user_groups
							WHERE user_id = ?' . ($userGroupId?' AND user_group_id = ?':''),
						$params);
	}

	/**
	 * Remove all user group assignments for a given group
	 * @param int $userGroupId
	 */
	function deleteAssignmentsByUserGroupId($userGroupId) {
		return $this->update('DELETE FROM user_user_groups
							WHERE user_group_id = ?',
						(int) $userGroupId);
	}

	/**
	 * Remove all user group assignments in a given press
	 * @param int $pressId
	 */
	function deleteByPressId($pressId) {
		$result =& $this->retrieve(
						'SELECT uug.user_group_id, uug.user_id
						FROM user_groups ug JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
						WHERE ug.press_id = ?', $pressId);

		$assignments =& new DAOResultFactory($result, $this, '_returnFromRow');
		while ( !$assignments->eof() ) {
			$assignment =& $assignments->next();
			$this->deleteByUserId($assignment->getUserId(), $assignment->getUserGroupId());
			unset($assignment);
		}
		return $assignments;
	}


	/**
	 * Retrieve user group assignments for a user
	 * @param $userId int
	 * @param $pressId int
	 * @return Iterator UserGroup
	 */
	function &getByUserId($userId, $pressId = null){
		$params = array($userId);
		if ( $pressId ) $params[] = $pressId;
		$result =& $this->retrieve(
			'SELECT uug.user_group_id, uug.user_id
				FROM user_groups ug JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
				WHERE uug.user_id = ?' . ($pressId?' AND ug.press_id = ?':''),
			$params);

		$returner = new DAOResultFactory($result, $this, '_returnFromRow');
		return $returner;
	}


	/**
	 * Insert an assignment
	 * @param $userId
	 * @param $groupId
	 */
	function insertAssignment(&$userGroupAssignment) {
		$returner =& $this->update(
			'INSERT INTO user_user_groups SET user_id = ?, user_group_id = ?',
			array($userGroupAssignment->getUserId(), $userGroupAssignment->getUserGroupId())
			);
		return $returner;
	}

	/**
	 * Remove an assignment
	 * @param $userGroupAssignment
	 */
	function deleteAssignment(&$userGroupAssignment) {
		$returner =& $this->update(
			'DELETE FROM user_user_groups WHERE user_id = ? AND user_group_id = ?',
			array($userGroupAssignment->getUserId(), $userGroupAssignment->getUserGroupId()));
		return $returner;
	}

}

?>