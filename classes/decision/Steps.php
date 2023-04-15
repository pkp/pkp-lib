<?php
/**
 * @file classes/decision/Steps.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Steps
 *
 * @brief A base class to define a step-by-step workflow to record a decision type
 */

namespace PKP\decision;

use APP\facades\Repo;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class Steps
{
    public DecisionType $decisionType;
    public Submission $submission;
    public Context $context;
    public ?ReviewRound $reviewRound;
    public array $steps = [];

    public function __construct(DecisionType $decisionType, Submission $submission, Context $context, ?ReviewRound $reviewRound = null)
    {
        $this->decisionType = $decisionType;
        $this->submission = $submission;
        $this->context = $context;
        if ($reviewRound) {
            $this->reviewRound = $reviewRound;
        }
    }

    /**
     * Add a step to the workflow
     *
     * @param bool $prepend Pass true to add this step before other steps
     */
    public function addStep(Step $step, bool $prepend = false)
    {
        if ($prepend) {
            array_unshift($this->steps, $step);
        } else {
            $this->steps[$step->id] = $step;
        }
    }

    /**
     * Compile initial state data to pass to the frontend
     *
     * @see DecisionPage.vue
     */
    public function getState(): array
    {
        $state = [];
        foreach ($this->steps as $step) {
            $state[] = $step->getState();
        }
        return $state;
    }

    /**
     * Get all users assigned to a role in this decision's stage
     *
     * @param integer $roleId
     *
     * @return array<User>
     */
    public function getStageParticipants(int $roleId): array
    {
        /** @var StageAssignmentDAO $stageAssignmentDao  */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userIds = [];
        $result = $stageAssignmentDao->getBySubmissionAndRoleIds(
            $this->submission->getId(),
            [$roleId],
            $this->decisionType->getStageId()
        );
        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $result->next()) {
            $userIds[] = (int) $stageAssignment->getUserId();
        }
        $users = [];
        foreach (array_unique($userIds) as $authorUserId) {
            $users[] = Repo::user()->get($authorUserId);
        }

        return $users;
    }

    /**
     * Get all reviewers who completed a review in this decision's stage
     *
     * @param array<ReviewAssignments> $reviewAssignments
     *
     * @return array<User>
     */
    public function getReviewersFromAssignments(array $reviewAssignments): array
    {
        $reviewers = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            $reviewers[] = Repo::user()->get((int) $reviewAssignment->getReviewerId());
        }
        return $reviewers;
    }

    /**
     * Get all assigned editors who can make a decision in this stage
     */
    public function getDecidingEditors(): array
    {
        /** @var StageAssignmentDAO $stageAssignmentDao  */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userIds = $stageAssignmentDao->getDecidingEditorIds($this->submission->getId(), $this->decisionType->getStageId());
        $users = [];
        foreach (array_unique($userIds) as $authorUserId) {
            $users[] = Repo::user()->get($authorUserId);
        }

        return $users;
    }
}
