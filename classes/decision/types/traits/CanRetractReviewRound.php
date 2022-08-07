<?php

/**
 * @file classes/decision/types/traits/CanRetractReviewRound.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions to determine if a decision can be retracted from external/internal review round
 */

namespace PKP\decision\types\traits;

use APP\submission\Submission;

trait CanRetractReviewRound
{
    /**
     * Determine if can back out form current external review round to previous external review round
     *
     * The determining process follows as :
     *      If there is any submitted review by reviewer that is not cancelled, can not back out
     *      If there is any completed review by reviewer, can not back out
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        if (!$reviewRoundId) {
            return false;
        }

        $confirmedReviewerIds = $this->getReviewerIds($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_CONFIRMED);
        if (count($confirmedReviewerIds) > 0) {
            return false;
        }

        $completedReviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_COMPLETED);
        if (count($completedReviewAssignments) > 0) {
            return false;
        }

        return true;
    }
}
