<?php

/**
 * @file classes/stageAssignment/StageAssignmentDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StageAssignmentDAO
 * @ingroup stageAssignment
 * @see StageAssignment
 *
 * @brief Operations for retrieving and modifying StageAssignment objects.
 */

import('lib.pkp.classes.stageAssignment.StageAssignment');

class StageAssignmentDAO extends DAO {

	/**
	 * Retrieve an assignment by  its ID
	 * @param $stageAssignmentId int
	 * @return StageAssignment
	 */
	function getById($stageAssignmentId) {
		$result = $this->retrieve(
			$this->getBaseQueryForAssignmentSelection()
			. 'WHERE stage_assignment_id = ?',
			(int) $stageAssignmentId
		);
		return $this->_fromRow($result->GetRowAssoc(false));
	}

	/**
	 * Retrieve StageAssignments by submission and stage IDs.
	 * @param $submissionId int
	 * @param $stageId int (optional)
	 * @param $userGroupId int (optional)
	 * @param $userId int (optional)
	 * @return DAOResultFactory StageAssignment
	 */
	function getBySubmissionAndStageId($submissionId, $stageId = null, $userGroupId = null, $userId = null) {
		return $this->_getByIds($submissionId, $stageId, $userGroupId, $userId);
	}

	/**
	 * Retrieve StageAssignments by submission and role IDs.
	 * @param $submissionId int Submission ID
	 * @param $roleId int ROLE_ID_...
	 * @param $stageId int (optional)
	 * @param $userId int (optional)
	 * @return DAOResultFactory StageAssignment
	 */
	function getBySubmissionAndRoleId($submissionId, $roleId, $stageId = null, $userId = null) {
		return $this->_getByIds($submissionId, $stageId, null, $userId, $roleId);
	}

	/**
	 * Get by user ID
	 * @param $userId int
	 * @return StageAssignment
	 */
	function getByUserId($userId) {
		return $this->_getByIds(null, null, null, $userId);
	}


	/**
	 * Retrieve StageAssignments by submission and user IDs
	 * @param $submissionId int Submission ID
	 * @param $userId int User ID
	 * @param $stageId int optional WORKFLOW_STAGE_ID_...
	 * @return DAOResultFactory StageAssignment
	 */
	function getBySubmissionAndUserIdAndStageId($submissionId, $userId, $stageId = null) {
		return $this->_getByIds($submissionId, $stageId, null, $userId);
	}

	/**
	 * Get editor stage assignments.
	 * @param $submissionId int
	 * @param $stageId int
	 * @return array StageAssignment
	 */
	function getEditorsAssignedToStage($submissionId, $stageId) {
		$managerAssignmentFactory = $this->getBySubmissionAndRoleId($submissionId, ROLE_ID_MANAGER, $stageId);
		$subEditorAssignmentFactory = $this->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, $stageId);
		return array_merge($managerAssignmentFactory->toArray(), $subEditorAssignmentFactory->toArray());
	}

	/**
	 * Test if an editor or a sub editor is assigned to the submission
	 * This test is used to determine what grid to place a submission into,
	 * and to know if the review stage can be started.
	 * @param $submissionId (int) The id of the submission being tested.
	 * @param $stageId (int) The id of the stage being tested.
	 * @return bool
	 */
	function editorAssignedToStage($submissionId, $stageId = null) {
		$params = array((int) $submissionId, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR);
		if ($stageId) $params[] = (int) $stageId;
		$result = $this->retrieve(
			'SELECT	COUNT(*)
			FROM	stage_assignments sa
				JOIN user_groups ug ON (sa.user_group_id = ug.user_group_id)
				JOIN user_group_stage ugs ON (ug.user_group_id = ugs.user_group_id)
			WHERE	sa.submission_id = ? AND
				ug.role_id IN (?, ?)' .
				($stageId?' AND ugs.stage_id = ?':''),
			$params
		);
		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all assignments by UserGroupId and ContextId
	 * @param $userGroupId int
	 * @param $contextId int
	 * @return DAOResultFactory
	 */
	function getByUserGroupId($userGroupId, $contextId) {
		$params = array(
			(int) $userGroupId, 
			(int) $contextId
		);

		$result = $this->retrieve(
			'SELECT * FROM stage_assignments sa'
			. ' JOIN submissions s ON s.submission_id = sa.submission_id'
			. ' WHERE sa.user_group_id = ? AND s.context_id = ?',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Fetch a stageAssignment by symbolic info, building it if needed.
	 * @param $submissionId int
	 * @param $userGroupId int
	 * @param $userId int
	 * @param $recommendOnly boolean
	 * @param $canChangeMetadata boolean
	 * @return StageAssignment
	 */
	function build($submissionId, $userGroupId, $userId, $recommendOnly = false, $canChangeMetadata = null) {
		if (!isset($canChangeMetadata)) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			/** @var $userGroup UserGroup */
			$userGroup = $userGroupDao->getById($userGroupId);

			$canChangeMetadata = $userGroup->getPermitMetadataEdit();
		}


		// If one exists, fetch and return.
		$stageAssignment = $this->getBySubmissionAndStageId($submissionId, null, $userGroupId, $userId);
		if (!$stageAssignment->wasEmpty()) return $stageAssignment;

		// Otherwise, build one.
		$stageAssignment = $this->newDataObject();
		$stageAssignment->setSubmissionId($submissionId);
		$stageAssignment->setUserGroupId($userGroupId);
		$stageAssignment->setUserId($userId);
		$stageAssignment->setRecommendOnly($recommendOnly);
		$stageAssignment->setCanChangeMetadata($canChangeMetadata);
		$this->insertObject($stageAssignment);
		$stageAssignment->setId($this->getInsertId());
		return $stageAssignment;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return StageAssignmentEntry
	 */
	function newDataObject() {
		return new StageAssignment();
	}

	/**
	 * Internal function to return an StageAssignment object from a row.
	 * @param $row array
	 * @return StageAssignment
	 */
	function _fromRow($row) {
		$stageAssignment = $this->newDataObject();

		$stageAssignment->setId($row['stage_assignment_id']);
		$stageAssignment->setSubmissionId($row['submission_id']);
		$stageAssignment->setUserId($row['user_id']);
		$stageAssignment->setUserGroupId($row['user_group_id']);
		$stageAssignment->setDateAssigned($row['date_assigned']);
		$stageAssignment->setStageId($row['stage_id']);
		$stageAssignment->setRecommendOnly($row['recommend_only']);
		$stageAssignment->setCanChangeMetadata($row['can_change_metadata']);

		return $stageAssignment;
	}

	/**
	 * Insert a new StageAssignment.
	 * @param $stageAssignment StageAssignment
	 */
	function insertObject($stageAssignment) {
		$this->update(
			sprintf(
				'INSERT INTO stage_assignments
					(submission_id, user_group_id, user_id, date_assigned, recommend_only, can_change_metadata)
				VALUES
					(?, ?, ?, %s, ?, ?)',
				$this->datetimeToDB(Core::getCurrentDate())
			),
			array(
				$stageAssignment->getSubmissionId(),
				$this->nullOrInt($stageAssignment->getUserGroupId()),
				$this->nullOrInt($stageAssignment->getUserId()),
				(int) $stageAssignment->getRecommendOnly(),
				(int) $stageAssignment->getCanChangeMetadata()
			)
		);
	}

	/**
	 * Update a new StageAssignment.
	 * @param $stageAssignment StageAssignment
	 */
	function updateObject($stageAssignment) {
		$this->update(
			sprintf(
				'UPDATE stage_assignments SET
					submission_id = ?,
					user_group_id = ?,
					user_id = ?,
					date_assigned = %s,
					recommend_only = ?,
					can_change_metadata = ?
				WHERE	stage_assignment_id = ?',
				$this->datetimeToDB(Core::getCurrentDate())
			),
			array(
				(int) $stageAssignment->getSubmissionId(),
				$this->nullOrInt($stageAssignment->getUserGroupId()),
				$this->nullOrInt($stageAssignment->getUserId()),
				(int) $stageAssignment->getRecommendOnly(),
				(int) $stageAssignment->getCanChangeMetadata(),
				(int) $stageAssignment->getId()
			)
		);
	}

	/**
	 * Delete a StageAssignment.
	 * @param $stageAssignment StageAssignment
	 */
	function deleteObject($stageAssignment) {
		$this->deleteByAll(
			$stageAssignment->getSubmissionId(),
			$stageAssignment->getUserGroupId(),
			$stageAssignment->getUserId()
		);
	}

	/**
	 * Delete a stageAssignment by matching on all fields.
	 * @param $submissionId int Submission ID
	 * @param $userGroupId int User group ID
	 * @param $userId int User ID
	 */
	function deleteByAll($submissionId, $userGroupId, $userId) {
		$this->update(
			'DELETE FROM stage_assignments
			WHERE	submission_id = ?
				AND user_group_id = ?
				AND user_id = ?',
			array((int) $submissionId, (int) $userGroupId, (int) $userId)
		);
	}

	/**
	 * Get the ID of the last inserted stage assignment.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('stage_assignments', 'stage_assignment_id');
	}

	/**
	 * Retrieve a stageAssignment by submission and stage IDs.
	 * Private method that holds most of the work.
	 * serves two purposes: returns a single assignment or returns a factory,
	 * depending on the calling context.
	 * @param $submissionId int
	 * @param $stageId int optional
	 * @param $userGroupId int optional
	 * @param $userId int optional
	 * @param $roleId int optional ROLE_ID_...
	 * @param $single bool specify if only one stage assignment (default is a ResultFactory)
	 * @return StageAssignment|ResultFactory Mixed, depending on $single
	 */
	function _getByIds($submissionId = null, $stageId = null, $userGroupId = null, $userId = null, $roleId = null, $single = false) {
		$conditions = array();
		$params = array();
		if (isset($submissionId)) {
			$conditions[] = 'sa.submission_id = ?';
			$params[] = (int) $submissionId;
		}
		if (isset($stageId)) {
			$conditions[] = 'ugs.stage_id = ?';
			$params[] = (int) $stageId;
		}
		if (isset($userGroupId)) {
			$conditions[] = 'sa.user_group_id = ?';
			$params[] = (int) $userGroupId;
		}
		if (isset($userId)) {
			$conditions[] = 'sa.user_id = ?';
			$params[] = (int) $userId;
		}

		if (isset($roleId)) {
			$conditions[] = 'ug.role_id = ?';
			$params[] = (int) $roleId;
		}

		$result = $this->retrieve(
			$this->getBaseQueryForAssignmentSelection() .
			(isset($roleId)?' LEFT JOIN user_groups ug ON sa.user_group_id = ug.user_group_id ':'') .
			'WHERE ' . (implode(' AND ', $conditions)),
			$params
		);

		if ($single) {
			// all four parameters must be specified for a single record to be returned
			if (!$submissionId && !$stageId && !$userGroupId && !$userId) return false;
			// no matches were found.
			if ($result->RecordCount() == 0) return false;
			$returner = $this->_fromRow($result->GetRowAssoc(false));
			$result->Close();
			return $returner;
		} else {
			// In any other case, return a list of all assignments
			return new DAOResultFactory($result, $this, '_fromRow');
		}
	}

	/**
	 * Base query to select an stage assignment.
	 * @return string
	 */
	function getBaseQueryForAssignmentSelection() {
		return 'SELECT ugs.stage_id AS stage_id, sa.* FROM stage_assignments sa
			JOIN user_group_stage ugs ON sa.user_group_id = ugs.user_group_id ';
	}
}


