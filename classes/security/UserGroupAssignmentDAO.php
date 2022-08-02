<?php

/**
 * @file classes/security/UserGroupAssignmentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupAssignmentDAO
 * @ingroup security
 *
 * @see UserGroupAssigment
 *
 * @brief Operations for retrieving and modifying user group assignments
 * FIXME: Some of the context-specific features of this class will have
 * to be changed for zero- or double-context applications when user groups
 * are ported over to them.
 */

namespace PKP\security;

use Illuminate\Support\Facades\DB;
use PKP\db\DAOResultFactory;

class UserGroupAssignmentDAO extends \PKP\db\DAO
{
    /**
     * Create a new UserGroupAssignment object
     * (allows extensibility)
     */
    public function newDataObject()
    {
        return new UserGroupAssignment();
    }

    /**
     * Internal function to return a UserGroupAssignment object from a row.
     *
     * @param array $row
     *
     * @return Role
     */
    public function _fromRow($row)
    {
        $userGroupAssignment = $this->newDataObject();
        $userGroupAssignment->setUserGroupId($row['user_group_id']);
        $userGroupAssignment->setUserId($row['user_id']);

        return $userGroupAssignment;
    }

    /**
     * Delete all user group assignments for a given userId
     *
     * @param int $userId
     * @param int $userGroupId optional
     */
    public function deleteByUserId($userId, $userGroupId = null)
    {
        $params = [(int) $userId];
        if ($userGroupId) {
            $params[] = (int) $userGroupId;
        }

        $this->update(
            'DELETE FROM user_user_groups
			WHERE	user_id = ?
			' . ($userGroupId ? ' AND user_group_id = ?' : ''),
            $params
        );
    }

    /**
     * Remove all user group assignments for a given group
     *
     * @param int $userGroupId
     */
    public function deleteAssignmentsByUserGroupId($userGroupId)
    {
        return $this->update('DELETE FROM user_user_groups WHERE user_group_id = ?', [(int) $userGroupId]);
    }

    /**
     * Remove all user group assignments in a given context
     *
     * @param int $contextId
     * @param int $userId
     */
    public function deleteAssignmentsByContextId($contextId, $userId = null)
    {
        $params = [(int) $contextId];
        if ($userId) {
            $params[] = (int) $userId;
        }
        $result = $this->retrieve(
            'SELECT	uug.user_group_id, uug.user_id
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
			WHERE	ug.context_id = ?
				' . ($userId ? ' AND uug.user_id = ?' : ''),
            $params
        );

        $assignments = new DAOResultFactory($result, $this, '_fromRow');
        while ($assignment = $assignments->next()) {
            $this->deleteByUserId($assignment->getUserId(), $assignment->getUserGroupId());
        }
    }


    /**
     * Retrieve user group assignments for a user
     *
     * @param int $userId
     * @param int $contextId
     * @param int $roleId
     *
     * @return Iterator UserGroup
     */
    public function getByUserId($userId, $contextId = null, $roleId = null)
    {
        $params = [(int) $userId];
        if ($contextId) {
            $params[] = (int) $contextId;
        }
        if ($roleId) {
            $params[] = (int) $roleId;
        }

        $result = $this->retrieve(
            'SELECT uug.user_group_id, uug.user_id
			FROM	user_groups ug
				JOIN user_user_groups uug ON ug.user_group_id = uug.user_group_id
				WHERE uug.user_id = ?' .
                ($contextId ? ' AND ug.context_id = ?' : '') .
                ($roleId ? ' AND ug.role_id = ?' : ''),
            $params
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }


    /**
     * Insert an assignment
     */
    public function insertObject($userGroupAssignment)
    {
        DB::table('user_user_groups')->updateOrInsert([
            'user_id' => (int) $userGroupAssignment->getUserId(),
            'user_group_id' => (int) $userGroupAssignment->getUserGroupId(),
        ]);
    }

    /**
     * Remove an assignment
     *
     * @param UserGroupAssignment $userGroupAssignment
     */
    public function deleteAssignment($userGroupAssignment)
    {
        $this->update(
            'DELETE FROM user_user_groups WHERE user_id = ? AND user_group_id = ?',
            [(int) $userGroupAssignment->getUserId(), (int) $userGroupAssignment->getUserGroupId()]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\UserGroupAssignmentDAO', '\UserGroupAssignmentDAO');
}
