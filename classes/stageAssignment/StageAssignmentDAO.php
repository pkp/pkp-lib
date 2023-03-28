<?php

/**
 * @file classes/stageAssignment/StageAssignmentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageAssignmentDAO
 * @ingroup stageAssignment
 *
 * @see StageAssignment
 *
 * @brief Operations for retrieving and modifying StageAssignment objects.
 */

namespace PKP\stageAssignment;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\db\DAOResultFactory;
use PKP\security\Role;

class StageAssignmentDAO extends \PKP\db\DAO
{
    /**
     * Retrieve an assignment by  its ID
     *
     * @param int $stageAssignmentId
     *
     * @return StageAssignment
     */
    public function getById($stageAssignmentId)
    {
        $result = $this->retrieve(
            $this->getBaseQueryForAssignmentSelection()
            . 'WHERE stage_assignment_id = ?',
            [(int) $stageAssignmentId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve StageAssignments by submission and stage IDs.
     *
     * @param int $submissionId
     * @param int $stageId (optional)
     * @param int $userGroupId (optional)
     * @param int $userId (optional)
     *
     * @return DAOResultFactory StageAssignment
     */
    public function getBySubmissionAndStageId($submissionId, $stageId = null, $userGroupId = null, $userId = null)
    {
        return $this->_getByIds($submissionId, $stageId, $userGroupId, $userId);
    }

    /**
     * Retrieve StageAssignments by submission and role IDs.
     *
     * @param int $submissionId Submission ID
     * @param int[] $roleIds [ROLE_ID_...]
     * @param int $stageId (optional)
     * @param int $userId (optional)
     *
     * @return DAOResultFactory StageAssignment
     */
    public function getBySubmissionAndRoleIds($submissionId, $roleIds, $stageId = null, $userId = null)
    {
        return $this->_getByIds($submissionId, $stageId, null, $userId, $roleIds);
    }

    /**
     * Deprecated. Use self::getBySubmissionAndRoleIds() instead
     *
     * @param int $submissionId Submission ID
     * @param int $roleId ROLE_ID_...
     * @param int $stageId (optional)
     * @param int $userId (optional)
     *
     * @return DAOResultFactory StageAssignment
     *
     * @deprecated 3.4
     */
    public function getBySubmissionAndRoleId($submissionId, $roleId, $stageId = null, $userId = null)
    {
        return $this->getBySubmissionAndRoleIds($submissionId, [$roleId], $stageId, $userId);
    }

    /**
     * Get by user ID
     *
     * @param int $userId
     *
     * @return StageAssignment
     */
    public function getByUserId($userId)
    {
        return $this->_getByIds(null, null, null, $userId);
    }


    /**
     * Retrieve StageAssignments by submission and user IDs
     *
     * @param int $submissionId Submission ID
     * @param int $userId User ID
     * @param int $stageId optional WORKFLOW_STAGE_ID_...
     *
     * @return DAOResultFactory StageAssignment
     */
    public function getBySubmissionAndUserIdAndStageId($submissionId, $userId, $stageId = null)
    {
        return $this->_getByIds($submissionId, $stageId, null, $userId);
    }

    /**
     * Get editor stage assignments.
     *
     * @param int $submissionId
     * @param int $stageId
     *
     * @return array StageAssignment
     */
    public function getEditorsAssignedToStage($submissionId, $stageId)
    {
        $assignmentFactory = $this->getBySubmissionAndRoleIds($submissionId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $stageId);
        return $assignmentFactory->toArray();
    }

    /**
     * Test if an editor or a sub editor is assigned to the submission
     * This test is used to determine what grid to place a submission into,
     * and to know if the review stage can be started.
     *
     * @param int $submissionId The id of the submission being tested.
     * @param int $stageId The id of the stage being tested.
     *
     * @return bool
     */
    public function editorAssignedToStage($submissionId, $stageId = null)
    {
        $params = [(int) $submissionId, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
        if ($stageId) {
            $params[] = (int) $stageId;
        }
        $result = $this->retrieve(
            'SELECT	COUNT(*) AS row_count
			FROM	stage_assignments sa
				JOIN user_groups ug ON (sa.user_group_id = ug.user_group_id)
				JOIN user_group_stage ugs ON (ug.user_group_id = ugs.user_group_id)
			WHERE	sa.submission_id = ? AND
				ug.role_id IN (?, ?)' .
                ($stageId ? ' AND ugs.stage_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row && $row->row_count;
    }

    /**
     * Get all assigned editors who can make a decision in a given stage
     *
     * @return array<int>
     */
    public function getDecidingEditorIds(int $submissionId, int $stageId): array
    {
        $decidingEditorIds = [];
        $result = $this->getBySubmissionAndRoleIds(
            $submissionId,
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            $stageId
        );
        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $result->next()) {
            if (!$stageAssignment->getRecommendOnly()) {
                $decidingEditorIds[] = (int) $stageAssignment->getUserId();
            }
        }
        return $decidingEditorIds;
    }

    /**
     * Retrieve all assignments by UserGroupId and ContextId
     *
     * @param int $userGroupId
     * @param int $contextId
     *
     * @return DAOResultFactory
     */
    public function getByUserGroupId($userGroupId, $contextId)
    {
        $result = $this->retrieve(
            'SELECT * FROM stage_assignments sa'
            . ' JOIN submissions s ON s.submission_id = sa.submission_id'
            . ' WHERE sa.user_group_id = ? AND s.context_id = ?',
            [(int) $userGroupId, (int) $contextId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Fetch a stageAssignment by symbolic info, building it if needed.
     *
     * @param int $submissionId
     * @param int $userGroupId
     * @param int $userId
     * @param bool $recommendOnly
     * @param bool $canChangeMetadata
     *
     * @return StageAssignment
     */
    public function build($submissionId, $userGroupId, $userId, $recommendOnly = false, $canChangeMetadata = null)
    {
        if (!isset($canChangeMetadata)) {
            /** @var UserGroup $userGroup */
            $userGroup = Repo::userGroup()->get($userGroupId);

            $canChangeMetadata = $userGroup->getPermitMetadataEdit();
        }


        // If one exists, fetch and return.
        $stageAssignments = $this->getBySubmissionAndStageId($submissionId, null, $userGroupId, $userId);
        if ($stageAssignment = $stageAssignments->next()) {
            return $stageAssignment;
        }

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
     *
     * @return StageAssignmentEntry
     */
    public function newDataObject()
    {
        return new StageAssignment();
    }

    /**
     * Internal function to return an StageAssignment object from a row.
     *
     * @param array $row
     *
     * @return StageAssignment
     */
    public function _fromRow($row)
    {
        $stageAssignment = $this->newDataObject();

        $stageAssignment->setId($row['stage_assignment_id']);
        $stageAssignment->setSubmissionId($row['submission_id']);
        $stageAssignment->setUserId($row['user_id']);
        $stageAssignment->setUserGroupId($row['user_group_id']);
        $stageAssignment->setDateAssigned($row['date_assigned']);
        $stageAssignment->setStageId($row['stage_id']);
        $stageAssignment->setRecommendOnly((bool) $row['recommend_only']);
        $stageAssignment->setCanChangeMetadata($row['can_change_metadata']);

        return $stageAssignment;
    }

    /**
     * Insert a new StageAssignment.
     *
     * @param StageAssignment $stageAssignment
     */
    public function insertObject($stageAssignment)
    {
        $this->update(
            sprintf(
                'INSERT INTO stage_assignments
					(submission_id, user_group_id, user_id, date_assigned, recommend_only, can_change_metadata)
				VALUES
					(?, ?, ?, %s, ?, ?)',
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [
                $stageAssignment->getSubmissionId(),
                $this->nullOrInt($stageAssignment->getUserGroupId()),
                $this->nullOrInt($stageAssignment->getUserId()),
                (int) $stageAssignment->getRecommendOnly(),
                (int) $stageAssignment->getCanChangeMetadata()
            ]
        );
    }

    /**
     * Update a new StageAssignment.
     *
     * @param StageAssignment $stageAssignment
     */
    public function updateObject($stageAssignment)
    {
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
            [
                (int) $stageAssignment->getSubmissionId(),
                $this->nullOrInt($stageAssignment->getUserGroupId()),
                $this->nullOrInt($stageAssignment->getUserId()),
                (int) $stageAssignment->getRecommendOnly(),
                (int) $stageAssignment->getCanChangeMetadata(),
                (int) $stageAssignment->getId()
            ]
        );
    }

    /**
     * Delete a StageAssignment.
     *
     * @param StageAssignment $stageAssignment
     */
    public function deleteObject($stageAssignment)
    {
        $this->deleteByAll(
            $stageAssignment->getSubmissionId(),
            $stageAssignment->getUserGroupId(),
            $stageAssignment->getUserId()
        );
    }

    /**
     * Delete a stageAssignment by matching on all fields.
     *
     * @param int $submissionId Submission ID
     * @param int $userGroupId User group ID
     * @param int $userId User ID
     */
    public function deleteByAll($submissionId, $userGroupId, $userId)
    {
        $this->update(
            'DELETE FROM stage_assignments
			WHERE	submission_id = ?
				AND user_group_id = ?
				AND user_id = ?',
            [(int) $submissionId, (int) $userGroupId, (int) $userId]
        );
    }

    /**
     * Retrieve a stageAssignment by submission and stage IDs.
     * Private method that holds most of the work.
     * serves two purposes: returns a single assignment or returns a factory,
     * depending on the calling context.
     *
     * @param int $submissionId
     * @param int $stageId optional
     * @param int $userGroupId optional
     * @param int $userId optional
     * @param int[] $roleIds optional [ROLE_ID_...]
     * @param bool $single specify if only one stage assignment (default is a ResultFactory)
     *
     * @return StageAssignment|ResultFactory Mixed, depending on $single
     */
    public function _getByIds($submissionId = null, $stageId = null, $userGroupId = null, $userId = null, $roleIds = null, $single = false)
    {
        $conditions = [];
        $params = [];
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

        if (isset($roleIds)) {
            $sanitizedRoleIds = join(',', array_map(fn ($roleId) => (int) $roleId, (array) $roleIds));
            $conditions[] = 'ug.role_id IN (' . $sanitizedRoleIds . ')';
        }

        $result = $this->retrieve(
            $this->getBaseQueryForAssignmentSelection() .
            (isset($roleIds) ? ' LEFT JOIN user_groups ug ON sa.user_group_id = ug.user_group_id ' : '') .
            'WHERE ' . (implode(' AND ', $conditions)),
            $params
        );

        if ($single) {
            // all four parameters must be specified for a single record to be returned
            if (!$submissionId && !$stageId && !$userGroupId && !$userId) {
                return false;
            }
            // no matches were found.
            if ($row = $result->current()) {
                return $this->_fromRow((array) $row);
            }
            return false;
        }
        // In any other case, return a list of all assignments
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Base query to select an stage assignment.
     *
     * @return string
     */
    public function getBaseQueryForAssignmentSelection()
    {
        return 'SELECT ugs.stage_id AS stage_id, sa.* FROM stage_assignments sa
			JOIN user_group_stage ugs ON sa.user_group_id = ugs.user_group_id ';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\stageAssignment\StageAssignmentDAO', '\StageAssignmentDAO');
}
