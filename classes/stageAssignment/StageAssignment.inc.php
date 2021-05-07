<?php

/**
 * @defgroup stageAssignment Stage Assignment
 * Implements Stage Assignments, which describe the assignment of users to
 * stages (discrete parts of the workflow, e.g. Internal Review or Production).
 */

/**
 * @file classes/stageAssignment/StageAssignment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageAssignment
 * @ingroup stageAssignment
 *
 * @see StageAssignmentDAO
 *
 * @brief Basic class describing a Stage Assignment.
 */

namespace PKP\stageAssignment;

class StageAssignment extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //
    /**
     * Set the submission ID
     *
     * @param $submissionId int
     */
    public function setSubmissionId($submissionId)
    {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Get the submission ID
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->getData('submissionId');
    }

    /**
     * Set the stage ID
     *
     * @param $stageId int
     */
    public function setStageId($stageId)
    {
        $this->setData('stageId', $stageId);
    }

    /**
     * Get the stage ID
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getData('stageId');
    }

    /**
     * Set the User Group ID
     *
     * @param $userGroupId int
     */
    public function setUserGroupId($userGroupId)
    {
        $this->setData('userGroupId', $userGroupId);
    }

    /**
     * Get the User Group ID
     *
     * @return int
     */
    public function getUserGroupId()
    {
        return $this->getData('userGroupId');
    }

    /**
     * Get user ID for this stageAssignment.
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * Set user ID for this stageAssignment.
     *
     * @param $userId int
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }

    /**
     * Set the date assigned
     *
     * @param $dateAssigned datestamp (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateAssigned($dateAssigned)
    {
        $this->setData('dateAssigned', $dateAssigned);
    }

    /**
     * Get the date assigned
     *
     * @return datestamp (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateAssigned()
    {
        return $this->getData('dateAssigned');
    }

    /**
     * Get recommendOnly option.
     *
     * @return boolean
     */
    public function getRecommendOnly()
    {
        return $this->getData('recommendOnly');
    }

    /**
     * Set recommendOnly option.
     *
     * @param $recommendOnly boolean
     */
    public function setRecommendOnly($recommendOnly)
    {
        $this->setData('recommendOnly', $recommendOnly);
    }

    /**
     * Get permit metadata edit option.
     *
     * @return boolean
     */
    public function getCanChangeMetadata()
    {
        return $this->getData('canChangeMetadata');
    }

    /**
     * Set permit metadata edit option.
     */
    public function setCanChangeMetadata($canChangeMetadata)
    {
        $this->setData('canChangeMetadata', $canChangeMetadata);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\stageAssignment\StageAssignment', '\StageAssignment');
}
