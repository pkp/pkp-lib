<?php

/**
 * @file controllers/grid/queries/QueriesAccessHelper.inc.php
 *
 * Copyright (c) 2016-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueriesAccessHelper
 * @ingroup controllers_grid_query
 *
 * @brief Implements access rules for queries.
 * Permissions are intended as follows (per UI/UX group, 2015-12-01)
 * Added permissions for Reviewers, 2017-11-05
 *
 *	ROLE
 *  TASK       MANAGER   SUB EDITOR  ASSISTANT  AUTHOR      REVIEWER
 *  Create Q   Yes       Yes	     Yes        Yes         Yes
 *  Edit Q     All       All         If Creator If Creator  if Creator
 *  List/View  All       All	     Assigned   Assigned    Assigned
 *  Open/close All       All	     Assigned   No          No
 *  Delete Q   All       All	     If Blank	If Blank    If Blank
 */

class QueriesAccessHelper {
	/** @var array */
	var $_authorizedContext;

	/** @var User */
	var $_user;

	/**
	 * Constructor
	 * @param $authorizedContext array
	 * @param $user User
	 */
	function __construct($authorizedContext, $user) {
		$this->_authorizedContext = $authorizedContext;
		$this->_user = $user;
	}

	/**
	 * Retrieve authorized context objects from the authorized context.
	 * @param $assocType integer any of the ASSOC_TYPE_* constants
	 * @return mixed
	 */
	function getAuthorizedContextObject($assocType) {
		return isset($this->_authorizedContext[$assocType])?$this->_authorizedContext[$assocType]:null;
	}

	/**
	 * Determine whether the current user can open/close a query.
	 * @param $query Query
	 * @return boolean True if the user is allowed to open/close the query.
	 */
	function getCanOpenClose($query) {
		// Managers and sub editors are always allowed
		if ($this->hasStageRole($query->getStageId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR))) return true;

		// Assigned assistants are allowed
		if ($this->hasStageRole($query->getStageId(), array(ROLE_ID_ASSISTANT)) && $this->isAssigned($this->_user->getId(), $query->getId())) return true;

		// Otherwise, not allowed.
		return false;
	}

	/**
	 * Determine whether the user can re-order the queries.
	 * @param $stageId int
	 * @return boolean
	 */
	function getCanOrder($stageId) {
		return $this->hasStageRole($stageId, array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR));
	}

	/**
	 * Determine whether the user can create queries.
	 * @param $stageId int
	 * @return boolean
	 */
	function getCanCreate($stageId) {
		return $this->hasStageRole($stageId, array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER));
	}

	/**
	 * Determine whether the current user can edit a query.
	 * @param $queryId int Query ID
	 * @return boolean True iff the user is allowed to edit the query.
	 */
	function getCanEdit($queryId) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$query = $queryDao->getById($queryId);
		if (!$query) return false;

		// Assistants, authors and reviewers are allowed, if they created the query
		if ($this->hasStageRole($query->getStageId(), array(ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER))) {
			if ($query->getHeadNote()->getUserId() == $this->_user->getId()) return true;
		}

		// Managers are always allowed
		if ($this->hasStageRole($query->getStageId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR))) return true;

		// Otherwise, not allowed.
		return false;
	}

	/**
	 * Determine whether the current user can delete a query.
	 * @param $queryId int Query ID
	 * @return boolean True iff the user is allowed to delete the query.
	 */
	function getCanDelete($queryId) {
		// Users can always delete their own placeholder queries.
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$query = $queryDao->getById($queryId);
		if ($query) {
			$headNote = $query->getHeadNote();
			if ($headNote->getUserId() == $this->_user->getId() && $headNote->getTitle()=='') return true;
		}

		// Managers are always allowed
		if ($this->hasStageRole($query->getStageId(), array(ROLE_ID_MANAGER))) return true;

		// Otherwise, not allowed.
		return false;
	}


	/**
	 * Determine whether the current user can list all queries on the submission
	 * @param $stageId int The stage ID to load discussions for
	 * @return boolean
	 */
	function getCanListAll($stageId) {
		return $this->hasStageRole($stageId, array(ROLE_ID_MANAGER));
	}

	/**
	 * Determine whether the current user is assigned to the current query.
	 * @param $userId int User ID
	 * @param $queryId int Query ID
	 * @return boolean
	 */
	protected function isAssigned($userId, $queryId) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		return (boolean) $queryDao->getParticipantIds($queryId, $userId);
	}

	/**
	 * Determine whether the current user has role(s) in the current workflow
	 * stage
	 * @param $stageId int
	 * @param $roles array [ROLE_ID_...]
	 * @return boolean
	 */
	protected function hasStageRole($stageId, $roles) {
		$stageRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		return !empty(array_intersect($stageRoles[$stageId], $roles));
	}
}


