<?php

/**
 * @file classes/security/RoleDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RoleDAO
 *
 * @ingroup security
 *
 * @deprecated Deprecated in 3.4; use the UserGroup repository and collector etc.
 *
 * @brief Operations for retrieving and modifying Role objects.
 */

namespace PKP\security;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\db\DAO;
use PKP\userGroup\UserGroup;

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
     */
    public function userHasRole(?int $contextId, int $userId, int|array $roleIds): bool
    {
        return DB::table('user_groups AS ug')
            ->join('user_user_groups AS uug', 'ug.user_group_id', '=', 'uug.user_group_id')
            ->where('uug.user_id', (int) $userId)
            ->whereIn('ug.role_id', is_array($roleIds) ? $roleIds : [$roleIds])
            ->where(fn (Builder $q) => $q->whereNull('uug.date_start')->orWhere('uug.date_start', '<=', Core::getCurrentDate()))
            ->where(fn (Builder $q) => $q->whereNull('uug.date_end')->orWhere('uug.date_end', '>', Core::getCurrentDate()))
            ->whereRaw('COALESCE(ug.context_id, 0) = ?', [(int) $contextId])
            ->exists();
    }

    /**
     * Return an array of row objects corresponding to the roles a given user has
     *
     * @return Role[]
     */
    public function getByUserId(int $userId, ?int $contextId = Application::SITE_CONTEXT_ID_ALL): array
    {
        $result = DB::table('user_groups AS ug')
            ->join('user_user_groups AS uug', 'ug.user_group_id', '=', 'uug.user_group_id')
            ->where('uug.user_id', $userId)
            ->where(fn (Builder $q) => $q->whereNull('uug.date_start')->orWhere('uug.date_start', '<=', Core::getCurrentDate()))
            ->where(fn (Builder $q) => $q->whereNull('uug.date_end')->orWhere('uug.date_end', '>', Core::getCurrentDate()))
            ->when($contextId !== Application::SITE_CONTEXT_ID_ALL, fn (Builder $q) => $q->whereRaw('COALESCE(ug.context_id, 0) = ?', [(int) $contextId]))
            ->distinct()
            ->select(['ug.role_id AS role_id'])
            ->get();

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
     * @return array{int: array<int, Role>}
     *
     */
    public function getByUserIdGroupedByContext(int $userId): array
    {
        $userGroups = UserGroup::query()
            ->with('userUserGroups')
            ->whereHas('userUserGroups', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where(function ($q) {
                        $q->whereNull('date_end')
                            ->orWhere('date_end', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('date_start')
                            ->orWhere('date_start', '<=', now());
                    });
            })
            ->get();

        $roles = [];
        foreach ($userGroups as $userGroup) {
            $role = $this->newDataObject();
            $role->setRoleId($userGroup->roleId);
            $roles[(int) $userGroup->contextId][$userGroup->roleId] = $role;
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
        return [Role::ROLE_ID_MANAGER];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\RoleDAO', '\RoleDAO');
}
