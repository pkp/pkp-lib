<?php

/**
 * @file classes/decision/types/traits/withReviewRound.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions to provide review round related details associated with submission
 */

namespace PKP\decision\types\traits;

use APP\submission\Submission;
use Exception;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;

trait withReviewRound
{
    /**
     * Determine if submission has only one review round associated with it
     */
    protected function isOnlyReviewRound(Submission $submission, int $stageId): bool
    {
        if (! in_array($stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
            throw new Exception('Not a valid review stage');
        }

        if ($stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            return $submission->getExternalReviewRoundCount() === 1;
        }

        return $submission->getInternalReviewRoundCount() === 1;
    }

    /**
     * Determine if submission has only multiple review round associated with it
     */
    protected function hasMultipleReviewRound(Submission $submission, int $stageId): bool
    {
        if (! in_array($stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
            throw new Exception('Not a valid review stage');
        }

        if ($stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            return $submission->getExternalReviewRoundCount() > 1;
        }

        return $submission->getInternalReviewRoundCount() > 1;
    }

    /**
     * Determine if submission has any review round associated with it for given stage
     */
    protected function hasReviewRound(Submission $submission, int $stageId): bool
    {
        if (! in_array($stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
            throw new Exception('Not a valid review stage');
        }

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        return $reviewRoundDao->submissionHasReviewRound($submission->getId(), $stageId);
    }

    /**
     * Determine if any reviewer assigned to the review round
     */
    protected function hasReviewerAssigned(int $reviewRoundId): bool
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        if ($reviewRoundDao->getAssignmentCountByid($reviewRoundId) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get all the review assignments associated with review round for given submission id
     *
     */
    protected function getRereviewAssignments(int $submissionId, int $reviewRoundId): array
    {
        /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');

        return $reviewAssignmentDao->getBySubmissionId(
            $submissionId,
            $reviewRoundId,
            $this->getStageId()
        );
    }

    /**
     * Get the assigned reviewers who completed their review
     *
     * @return array<int>
     */
    protected function getCompletedReviewerIds(Submission $submission, int $reviewRoundId): array
    {
        $userIds = [];
        $reviewAssignments = $this->getRereviewAssignments($submission->getId(), $reviewRoundId);

        foreach ($reviewAssignments as $reviewAssignment) {
            if (!in_array($reviewAssignment->getStatus(), ReviewAssignment::REVIEW_COMPLETE_STATUSES)) {
                continue;
            }
            $userIds[] = (int) $reviewAssignment->getReviewerId();
        }
        return $userIds;
    }

    /**
     * Get all the assigned reviewers id who currently in active for this round
     *
     * @return array<int>
     */
    protected function getActiveReviewersId(Submission $submission, int $reviewRoundId): array
    {
        $userIds = [];
        $reviewAssignments = $this->getRereviewAssignments($submission->getId(), $reviewRoundId);

        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getDeclined() || $reviewAssignment->getCancelled()) {
                continue;
            }
            $userIds[] = (int) $reviewAssignment->getReviewerId();
        }
        return $userIds;
    }

    /**
     * Get all the assigned reviewers who has confirm to review for this round
     *
     * @return array<int>
     */
    protected function getConfirmedReviewersId(Submission $submission, int $reviewRoundId): array
    {
        $userIds = [];
        $reviewAssignments = $this->getRereviewAssignments($submission->getId(), $reviewRoundId);

        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getDateConfirmed() && !$reviewAssignment->getCancelled()) {
                $userIds[] = (int) $reviewAssignment->getReviewerId();
            }
        }
        return $userIds;
    }

    /**
     * Get the completed review assignments for this round
     */
    protected function getCompletedReviewAssignments(int $submissionId, int $reviewRoundId): array
    {
        $reviewAssignments = $this->getRereviewAssignments($submissionId, $reviewRoundId);
        $completedReviewAssignments = [];

        foreach ($reviewAssignments as $reviewAssignment) {
            if (in_array($reviewAssignment->getStatus(), ReviewAssignment::REVIEW_COMPLETE_STATUSES)) {
                $completedReviewAssignments[] = $reviewAssignment;
            }
        }

        return $completedReviewAssignments;
    }

    /**
     * Get all the active review assignments for this round
     */
    protected function getActiveReviewAssignments(int $submissionId, int $reviewRoundId): array
    {
        $reviewAssignments = $this->getRereviewAssignments($submissionId, $reviewRoundId);
        $acceptedReviewAssignments = [];

        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getDeclined() || $reviewAssignment->getCancelled()) {
                continue;
            }
            $acceptedReviewAssignments[] = $reviewAssignment;
        }
        return $acceptedReviewAssignments;
    }

    /**
     * Get all the confirmed review assignment for this round
     *
     * @return array<int>
     */
    protected function getConfirmedReviewerAssignments(Submission $submission, int $reviewRoundId): array
    {
        $reviewAssignments = $this->getRereviewAssignments($submission->getId(), $reviewRoundId);
        $confirmedReviewAssignments = [];

        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getDateConfirmed() && !$reviewAssignment->getCancelled()) {
                $confirmedReviewAssignments[] = $reviewAssignment;
            }
        }
        return $confirmedReviewAssignments;
    }

    /**
     * Determine if submission has only any complted review assignments associated with it
     */
    protected function hasCompletedReviewAssginment(Submission $submission, int $reviewRoundId): bool
    {
        if (count($this->getCompletedReviewAssignments($submission->getId(), $reviewRoundId)) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Determine if any confirmed reviewer has been assigned who has not been cancelled
     */
    protected function hasConfirmedReviewer(Submission $submission, int $reviewRoundId): bool
    {
        if (count($this->getConfirmedReviewersId($submission, $reviewRoundId)) > 0) {
            return true;
        }

        return false;
    }
}
