<?php

/**
 * @file controllers/grid/queries/QueriesAccessHelper.php
 *
 * Copyright (c) 2016-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueriesAccessHelper
 *
 * @ingroup controllers_grid_query
 *
 * @brief Implements access rules for queries.
 * Permissions are intended as follows (per UI/UX group, 2015-12-01)
 * Added permissions for Reviewers, 2017-11-05
 *
 *             ROLE
 *  TASK       MANAGER/ADMIN  SUB EDITOR  ASSISTANT  AUTHOR      REVIEWER
 *  Create Q   Yes            Yes         Yes        Yes         Yes
 *  Edit Q     All            All         If Creator If Creator  if Creator
 *  List/View  All            All         Assigned   Assigned    Assigned
 *  Open/close All            All         Assigned   No          No
 *  Delete Q   All       All              If Blank   If Blank    If Blank
 */

namespace PKP\controllers\grid\queries;

use APP\core\Application;
use APP\facades\Repo;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\security\Role;
use PKP\user\User;

class QueriesAccessHelper
{
    /** @var array */
    public $_authorizedContext;

    /** @var User */
    public $_user;

    /**
     * Constructor
     *
     * @param array $authorizedContext
     * @param User $user
     */
    public function __construct($authorizedContext, $user)
    {
        $this->_authorizedContext = $authorizedContext;
        $this->_user = $user;
    }

    /**
     * Retrieve authorized context objects from the authorized context.
     *
     * @param int $assocType any of the Application::ASSOC_TYPE_* constants
     */
    public function getAuthorizedContextObject($assocType)
    {
        return $this->_authorizedContext[$assocType] ?? null;
    }

    /**
     * Determine whether the current user can open/close a query.
     *
     * @param Query $query
     *
     * @return bool True if the user is allowed to open/close the query.
     */
    public function getCanOpenClose($query)
    {
        // Managers and sub editors are always allowed
        if ($this->hasStageRole($query->stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR])) {
            return true;
        }

        // Assigned assistants are allowed
        if ($this->hasStageRole($query->stageId, [Role::ROLE_ID_ASSISTANT]) && $this->isAssigned($this->_user->getId(), $query->id)) {
            return true;
        }

        // Otherwise, not allowed.
        return false;
    }

    /**
     * Determine whether the user can re-order the queries.
     *
     * @param int $stageId
     *
     * @return bool
     */
    public function getCanOrder($stageId)
    {
        return $this->hasStageRole($stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR]);
    }

    /**
     * Determine whether the user can create queries.
     *
     * @param int $stageId
     *
     * @return bool
     */
    public function getCanCreate($stageId)
    {
        return $this->hasStageRole($stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER]);
    }

    /**
     * Determine whether the current user can edit a query.
     *
     * @param int $queryId Query ID
     *
     * @return bool True if the user is allowed to edit the query.
     */
    public function getCanEdit($queryId)
    {
        $query = Query::find($queryId);
        if (!$query) {
            return false;
        }

        // Assistants, authors and reviewers are allowed, if they created the query less than x seconds ago
        if ($this->hasStageRole($query->stageId, [Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER])) {
            $headNote = Repo::note()->getHeadNote($query->id);
            if ($headNote->userId == $this->_user->getId() && ($headNote->dateCreated->diffInHours() < 1)) {
                return true;
            }
        }

        // Managers are always allowed
        if ($this->hasStageRole($query->stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR])) {
            return true;
        }

        // Otherwise, not allowed.
        return false;
    }

    /**
     * Determine whether the current user can delete a query.
     *
     * @param int $queryId Query ID
     *
     * @return bool True if the user is allowed to delete the query.
     */
    public function getCanDelete($queryId)
    {
        // Users can always delete their own placeholder queries.
        $query = Query::find($queryId);
        if ($query) {
            $headNote = Repo::note()->getHeadNote($query->id);
            if ($headNote?->userId == $this->_user->getId() && $headNote?->title == '') {
                return true;
            }
        }

        // Managers and site admins are always allowed
        if ($this->hasStageRole($query->stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])) {
            return true;
        }

        // Otherwise, not allowed.
        return false;
    }


    /**
     * Determine whether the current user can list all queries on the submission
     *
     * @param int $stageId The stage ID to load discussions for
     *
     * @return bool
     */
    public function getCanListAll($stageId)
    {
        return $this->hasStageRole($stageId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
    }

    /**
     * Determine whether the current user is assigned to the current query.
     *
     * @param int $userId User ID
     * @param int $queryId Query ID
     *
     * @return bool
     */
    protected function isAssigned($userId, $queryId)
    {
        $participantIds = QueryParticipant::withQueryId($queryId)
            ->pluck('user_id')
            ->all();
        return in_array($userId, $participantIds);
    }

    /**
     * Determine whether the current user has role(s) in the current workflow
     * stage
     *
     * @param int $stageId
     * @param array $roles [ROLE_ID_...]
     *
     * @return bool
     */
    protected function hasStageRole($stageId, $roles)
    {
        $stageRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        return !empty(array_intersect($stageRoles[$stageId], $roles));
    }
}
