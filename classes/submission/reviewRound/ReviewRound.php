<?php

/**
 * @defgroup submission_reviewRound Review Round
 */
/**
 * @file classes/submission/reviewRound/ReviewRound.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRound
 * @ingroup submission_reviewRound
 *
 * @see ReviewRoundDAO
 *
 * @brief Basic class describing a review round.
 */

namespace PKP\submission\reviewRound;

use APP\decision\Decision;
use APP\facades\Repo;
use PKP\db\DAORegistry;

class ReviewRound extends \PKP\core\DataObject
{
    // The first four statuses are set explicitly by Decisions, which override
    // the current status.
    public const REVIEW_ROUND_STATUS_REVISIONS_REQUESTED = 1;
    public const REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW = 2;
    public const REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL = 3;
    public const REVIEW_ROUND_STATUS_ACCEPTED = 4;
    public const REVIEW_ROUND_STATUS_DECLINED = 5;

    // The following statuses are calculated based on the statuses of ReviewAssignments
    // in this round.
    public const REVIEW_ROUND_STATUS_PENDING_REVIEWERS = 6; // No reviewers have been assigned
    public const REVIEW_ROUND_STATUS_PENDING_REVIEWS = 7; // Waiting for reviews to be submitted by reviewers
    public const REVIEW_ROUND_STATUS_REVIEWS_READY = 8; // One or more reviews is ready for an editor to view
    public const REVIEW_ROUND_STATUS_REVIEWS_COMPLETED = 9; // All assigned reviews have been confirmed by an editor
    public const REVIEW_ROUND_STATUS_REVIEWS_OVERDUE = 10; // One or more reviews is overdue
    // The following status is calculated when the round is in REVIEW_ROUND_STATUS_REVISIONS_REQUESTED and
    // at least one revision file has been uploaded.
    public const REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED = 11;

    // The following statuses are calculated based on the statuses of recommendOnly EditorAssignments
    // and their decisions in this round.
    public const REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS = 12; // Waiting for recommendations to be submitted by recommendOnly editors
    public const REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY = 13; // One or more recommendations are ready for an editor to view
    public const REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED = 14; // All assigned recommendOnly editors have made a recommendation

    // The following status is calculated when the round is in REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW and
    // at least one revision file has been uploaded.
    public const REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED = 15;

    //
    // Get/set methods
    //

    /**
     * get submission id
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->getData('submissionId');
    }

    /**
     * set submission id
     *
     * @param int $submissionId
     */
    public function setSubmissionId($submissionId)
    {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Get review stage id (internal or external review).
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getData('stageId');
    }

    /**
     * Set review stage id
     *
     * @param int $stageId
     */
    public function setStageId($stageId)
    {
        $this->setData('stageId', $stageId);
    }

    /**
     * Get review round
     *
     * @return int
     */
    public function getRound()
    {
        return $this->getData('round');
    }

    /**
     * Set review round
     */
    public function setRound($round)
    {
        $this->setData('round', $round);
    }

    /**
     * Get current round status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * Set current round status
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->setData('status', $status);
    }

    /**
     * Calculate the status of this review round.
     *
     * If the round is in revisions, it will search for revision files and set
     * the status accordingly. If the round has not reached a revision status
     * yet, it will determine the status based on the statuses of the round's
     * ReviewAssignments.
     *
     * @return int
     */
    public function determineStatus()
    {
        // If revisions have been requested, check to see if any have been
        // submitted
        if ($this->getStatus() == self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED || $this->getStatus() == self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED) {
            // get editor decisions
            $pendingRevisionDecision = Repo::decision()->getActivePendingRevisionsDecision($this->getSubmissionId(), $this->getStageId(), Decision::PENDING_REVISIONS);

            if ($pendingRevisionDecision) {
                if (Repo::decision()->revisionsUploadedSinceDecision($pendingRevisionDecision, $this->getSubmissionId())) {
                    return self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED;
                }
            }
            return self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED;
        }

        // If revisions have been requested for re-submission, check to see if any have been
        // submitted
        if ($this->getStatus() == self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW || $this->getStatus() == self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED) {
            // get editor decisions
            $pendingRevisionDecision = Repo::decision()->getActivePendingRevisionsDecision($this->getSubmissionId(), $this->getStageId(), Decision::RESUBMIT);

            if ($pendingRevisionDecision) {
                if (Repo::decision()->revisionsUploadedSinceDecision($pendingRevisionDecision, $this->getSubmissionId())) {
                    return self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED;
                }
            }
            return self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW;
        }

        $statusFinished = in_array(
            $this->getStatus(),
            [
                self::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL,
                self::REVIEW_ROUND_STATUS_ACCEPTED,
                self::REVIEW_ROUND_STATUS_DECLINED
            ]
        );
        if ($statusFinished) {
            return $this->getStatus();
        }

        // Determine the round status by looking at the recommendOnly editor assignment statuses
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $pendingRecommendations = false;
        $recommendationsFinished = true;
        $recommendationsReady = false;
        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($this->getSubmissionId(), $this->getStageId());
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if ($editorsStageAssignment->getRecommendOnly()) {
                $pendingRecommendations = true;
                // Get recommendation from the assigned recommendOnly editor
                $decisions = Repo::decision()->getCount(
                    Repo::decision()
                        ->getCollector()
                        ->filterBySubmissionIds([$this->getSubmissionId()])
                        ->filterByStageIds([$this->getStageId()])
                        ->filterByReviewRoundIds([$this->getId()])
                        ->filterByEditorIds([$editorsStageAssignment->getUserId()])
                );
                if (!$decisions) {
                    $recommendationsFinished = false;
                } else {
                    $recommendationsReady = true;
                }
            }
        }
        if ($pendingRecommendations) {
            if ($recommendationsFinished) {
                return self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED;
            } elseif ($recommendationsReady) {
                return self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY;
            }
        }

        // Determine the round status by looking at the assignment statuses
        $anyOverdueReview = false;
        $anyIncompletedReview = false;
        $anyUnreadReview = false;
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignments = $reviewAssignmentDao->getByReviewRoundId($this->getId());
        foreach ($reviewAssignments as $reviewAssignment) {
            assert($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment);

            $assignmentStatus = $reviewAssignment->getStatus();

            switch ($assignmentStatus) {
                case REVIEW_ASSIGNMENT_STATUS_DECLINED:
                case REVIEW_ASSIGNMENT_STATUS_CANCELLED:
                    break;

                case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                    $anyOverdueReview = true;
                    break;

                case REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
                case REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
                    $anyIncompletedReview = true;
                    break;

                case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                    $anyUnreadReview = true;
                    break;
            }
        }

        // Find the correct review round status based on the state of
        // the current review assignments. The check order matters: the
        // first conditions override the others.
        if (empty($reviewAssignments)) {
            return self::REVIEW_ROUND_STATUS_PENDING_REVIEWERS;
        } elseif ($anyOverdueReview) {
            return self::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE;
        } elseif ($anyUnreadReview) {
            return self::REVIEW_ROUND_STATUS_REVIEWS_READY;
        } elseif ($anyIncompletedReview) {
            return self::REVIEW_ROUND_STATUS_PENDING_REVIEWS;
        } elseif ($pendingRecommendations) {
            return self::REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS;
        }
        return self::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED;
    }

    /**
     * Get locale key associated with current status
     *
     * @param bool $isAuthor True iff the status is to be shown to the author (slightly tweaked phrasing)
     *
     * @return int
     */
    public function getStatusKey($isAuthor = false)
    {
        switch ($this->determineStatus()) {
            case self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED:
                return 'editor.submission.roundStatus.revisionsRequested';
            case self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED:
                return 'editor.submission.roundStatus.revisionsSubmitted';
            case self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW:
                return 'editor.submission.roundStatus.resubmitForReview';
            case self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED:
                return 'editor.submission.roundStatus.submissionResubmitted';
            case self::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL:
                return 'editor.submission.roundStatus.sentToExternal';
            case self::REVIEW_ROUND_STATUS_ACCEPTED:
                return 'editor.submission.roundStatus.accepted';
            case self::REVIEW_ROUND_STATUS_DECLINED:
                return 'editor.submission.roundStatus.declined';
            case self::REVIEW_ROUND_STATUS_PENDING_REVIEWERS:
                return 'editor.submission.roundStatus.pendingReviewers';
            case self::REVIEW_ROUND_STATUS_PENDING_REVIEWS:
                return 'editor.submission.roundStatus.pendingReviews';
            case self::REVIEW_ROUND_STATUS_REVIEWS_READY:
                return $isAuthor ? 'author.submission.roundStatus.reviewsReady' : 'editor.submission.roundStatus.reviewsReady';
            case self::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED:
                return 'editor.submission.roundStatus.reviewsCompleted';
            case self::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE:
                return $isAuthor ? 'author.submission.roundStatus.reviewOverdue' : 'editor.submission.roundStatus.reviewOverdue';
            case self::REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS:
                return 'editor.submission.roundStatus.pendingRecommendations';
            case self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY:
                return 'editor.submission.roundStatus.recommendationsReady';
            case self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED:
                return 'editor.submission.roundStatus.recommendationsCompleted';
            default: return null;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewRound\ReviewRound', '\ReviewRound');
    foreach ([
        'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED',
        'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW',
        'REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL',
        'REVIEW_ROUND_STATUS_ACCEPTED',
        'REVIEW_ROUND_STATUS_DECLINED',
        'REVIEW_ROUND_STATUS_PENDING_REVIEWERS',
        'REVIEW_ROUND_STATUS_PENDING_REVIEWS',
        'REVIEW_ROUND_STATUS_REVIEWS_READY',
        'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED',
        'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE',
        'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED',
        'REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS',
        'REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY',
        'REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED',
        'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED',
    ] as $constantName) {
        define($constantName, constant('\ReviewRound::' . $constantName));
    }
}
