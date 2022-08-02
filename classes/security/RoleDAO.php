<?php

/**
 * @file classes/security/RoleDAO.php
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

namespace PKP\security;

use PKP\db\DAO;
use PKP\db\DAORegistry;

class RoleDAO extends DAO
{
    /**
     * Create new data object
     *
     * @return Role
     */
    public function newDataObject()
    {
        return new Role();
    }

    /**
     * Validation check to see if a user belongs to any group that has a given role
     *
     * @param int $contextId
     * @param int $userId
     * @param int|array $roleId ROLE_ID_...
     *
     * @return bool True iff at least one such role exists
     */
    public function userHasRole($contextId, $userId, $roleId)
    {
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
     *
     * @param int $userId
     * @param int $contextId
     *
     * @return array of Roles
     */
    public function getByUserId($userId, $contextId = null)
    {
        $params = [(int) $userId];
        if ($contextId !== null) {
            $params[] = (int) $contextId;
        }
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
     *
     * @param int $userId
     *
     * @return array
     */
    public function getByUserIdGroupedByContext($userId)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $userGroupsFactory = $userGroupDao->getByUserId($userId);

        $roles = [];
        while ($userGroup = $userGroupsFactory->next()) {
            $role = $roleDao->newDataObject();
            $role->setRoleId($userGroup->getRoleId());
            $roles[$userGroup->getContextId()][$userGroup->getRoleId()] = $role;
        }

        return $roles;
    }

    /**
     * Get role forbidden stages.
     *
     * @param int $roleId Specific role ID to fetch stages for, if any
     *
     * @return array With $roleId, array(WORKFLOW_STAGE_ID_...); without,
     *  array(ROLE_ID_... => array(WORKFLOW_STAGE_ID_...))
     */
    public function getForbiddenStages($roleId = null)
    {
        $forbiddenStages = [
            Role::ROLE_ID_MANAGER => [
                // Journal managers should always have all stage selections locked by default.
                WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
            ],
            Role::ROLE_ID_REVIEWER => [
                // Reviewer user groups should only have review stage assignments.
                WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
            ],
            Role::ROLE_ID_READER => [
                // Reader user groups should have no stage assignments.
                WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION,
            ],
        ];

        if ($roleId) {
            if (isset($forbiddenStages[$roleId])) {
                return $forbiddenStages[$roleId];
            } else {
                return [];
            }
        } else {
            return $forbiddenStages;
        }
    }

    /**
     *  All stages are always active for these permission levels.
     *
     * @return array array(ROLE_ID_MANAGER...);
     */
    public function getAlwaysActiveStages()
    {
        $alwaysActiveStages = [Role::ROLE_ID_MANAGER];
        return $alwaysActiveStages;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\RoleDAO', '\RoleDAO');
}
