<?php

/**
 * @file classes/decision/types/BackToPreviousExternalReviewRound.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to back out to previous external review round from current round
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\types\interfaces\DecisionRetractable;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\ToPreviousReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

class BackToPreviousExternalReviewRound extends DecisionType implements DecisionRetractable
{
    use ToPreviousReviewRound;
    use InExternalReviewRound;

    public function getDecision(): int
    {
        return Decision::BACK_TO_PREVIOUS_EXTERNAL_REVIEW_ROUND;
    }

    public function getNewStageId(): ?int
    {
        return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToPreviousExternalReviewRound.log';
    }

    /**
     * Determine if can back out form current external review round to previous external review round
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool
    {
        if (!$reviewRoundId) {
            return false;
        }

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        // if there is any submitted review by reviewer that is not cancelled
        // can not back out
        $confirmedReviewerIds = $this->getReviewerIds($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_CONFIRMED);
        if (count($confirmedReviewerIds) > 0) {
            return false;
        }

        // if this is the only round availabel
        // can not back out to previous round as there is none
        if ($reviewRoundDao->getReviewRoundCountBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) === 1) {
            return false;
        }

        // if there is any completed review by reviewer
        // can not back out
        $completedReviewAssignments = $this->getReviewAssignments($submission->getId(), $reviewRoundId, self::REVIEW_ASSIGNMENT_COMPLETED);
        if (count($completedReviewAssignments) > 0) {
            return false;
        }

        return true;
    }
}
