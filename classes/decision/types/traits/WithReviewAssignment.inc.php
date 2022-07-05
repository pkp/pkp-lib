<?php

/**
 * @file classes/decision/types/traits/WithReviewAssignment.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions to provide review assignments related details associated with submission
 */

namespace PKP\decision\types\traits;

use Exception;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;

trait WithReviewAssignment
{
    /**
     * Get all the review assignments based on review assignment states
     *
     * @param  int $submissionId            The targeted submission id
     * @param  int $reviewRoundId           The targeted review round id
     * @param  int $reviewAssignmentState   Review assignment state[active, completed, confirmed etc] based on reviews id get deduced
     *
     * @throws \Exception
     *
     * @return ReviewAssignment[]
     *
     */
    protected function getReviewAssignments(int $submissionId, int $reviewRoundId, int $reviewAssignmentState): array
    {

        /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');

        $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submissionId, $reviewRoundId, $this->getStageId());
        $assignments = [];

        foreach ($reviewAssignments as $reviewAssignment) {
            $valid = match ($reviewAssignmentState) {
                DecisionType::REVIEW_ASSIGNMENT_COMPLETED => in_array($reviewAssignment->getStatus(), ReviewAssignment::REVIEW_COMPLETE_STATUSES),
                DecisionType::REVIEW_ASSIGNMENT_ACTIVE => !$reviewAssignment->getDeclined() && !$reviewAssignment->getCancelled(),
                DecisionType::REVIEW_ASSIGNMENT_CONFIRMED => $reviewAssignment->getDateConfirmed() && !$reviewAssignment->getCancelled(),
                default => throw new Exception('Invalid review assignment state'),
            };

            if (!$valid) {
                continue;
            }

            $assignments[] = $reviewAssignment;
        }

        return $assignments;
    }

    /**
     * Get all the reviewers id based on review assignment states
     *
     * @param  int $submissionId            The targeted submission id
     * @param  int $reviewRoundId           The targeted review round id
     * @param  int $reviewAssignmentState   Review assignment state[active, completed, confirmed etc] based on reviews id get deduced
     *
     * @return array<int>
     */
    protected function getReviewerIds(int $submissionId, int $reviewRoundId, int $reviewAssignmentState): array
    {
        $assignments = $this->getReviewAssignments($submissionId, $reviewRoundId, $reviewAssignmentState);

        return collect($assignments)
            ->map(fn ($assignment) => $assignment->getReviewerId())
            ->toArray();
    }
}
