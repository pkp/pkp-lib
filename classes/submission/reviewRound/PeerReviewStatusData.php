<?php

/**
 * @file classes/submission/reviewRound/PeerReviewStatusData.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PeerReviewStatusData
 *
 * @ingroup submission_reviewRound
 *
 * @brief Processes review assignments/round info to produce public review status state and related dates.
 */

namespace PKP\submission\reviewRound;

use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\enums\PublicReviewStatus;

readonly class PeerReviewStatusData
{
    /** @var PublicReviewStatus Overall review state */
    public PublicReviewStatus $status;
    /** Oldest date a reviewer was assigned (helpful fallback when assignments present but not in progress) */
    public ?string $dateStarted;
    /** Oldest date a reviewer has accepted to complete a review (when in progress starts) */
    public ?string $dateInProgress;
    /** When the most recent review assignment has been completed if all assignments have been completed */
    public ?string $dateCompleted;

    public function __construct(
        PublicReviewStatus $status,
        ?string            $dateStarted = null,
        ?string            $dateInProgress = null,
        ?string            $dateCompleted = null,
    ) {
        $this->status = $status;
        $this->dateStarted = $dateStarted;
        $this->dateInProgress = $dateInProgress;
        $this->dateCompleted = $dateCompleted;
    }

    /**
     * Static constructor for building self from ReviewAssignment objects for a single review round
     *
     * @param Enumerable<int, ReviewAssignment> $reviewAssignments
     */
    public static function fromReviewAssignments(Enumerable $reviewAssignments, int $minimumRequiredReviews): self
    {
        // Review assignment should already be filtered for accepted only, but check just in case
        $eligibleAssignments = $reviewAssignments->filter(
            fn (ReviewAssignment $assignment) => !$assignment->getDeclined() && !$assignment->getCancelled()
        );

        // Oldest date assigned
        $dateStarted = $reviewAssignments
            ->map(fn (ReviewAssignment $assignment) => $assignment->getDateAssigned())
            ->filter()
            ->sort()
            ->first();

        // Oldest date confirmed
        $dateInProgress = $eligibleAssignments
            ->map(fn (ReviewAssignment $assignment) => $assignment->getDateConfirmed())
            ->filter()
            ->sort()
            ->first();
        $isInProgress = $dateInProgress !== null;

        $isNotStarted = $eligibleAssignments->isEmpty();

        // A review is only considered complete for public status purposes once an editor has confirmed it
        $confirmedCompleteAssignments = $eligibleAssignments->filter(
            fn (ReviewAssignment $assignment) => self::isCompleteAndConfirmed($assignment)
        );

        $allEligibleAssignmentsAreComplete = $confirmedCompleteAssignments->count() === $eligibleAssignments->count();

        $passesMinimumReviewCheck = self::meetsConfiguredMinimumReviewCount(
            $eligibleAssignments,
            $minimumRequiredReviews,
            requireEditorConfirmation: true
        );
        // Complete when reviews are done or when complete count passes minimum configured check
        // (the minimum is an advisory and does not block progression)
        // see also PKPReviewController::requestAuthorResponse()
        $isComplete = $allEligibleAssignmentsAreComplete || $passesMinimumReviewCheck;
        $dateCompleted = null;

        if ($isNotStarted) {
            $status = PublicReviewStatus::NotStarted;
        } elseif ($isComplete) {
            $status = PublicReviewStatus::Complete;

            // Most recent date completed
            $dateCompleted = $confirmedCompleteAssignments
                ->map(fn (ReviewAssignment $assignment) => $assignment->getDateCompleted())
                ->sort()
                ->last();
        } elseif ($isInProgress) {
            $status = PublicReviewStatus::InProgress;
        } else {
            $status = PublicReviewStatus::NotStarted;
        }

        return (new self($status, $dateStarted, $dateInProgress, $dateCompleted));
    }

    /**
     * Static constructor for building self from review round data based on assignment-level output from self.
     *
     * @param Enumerable<int, PeerReviewStatusData> $peerReviewDataCollection Required PeerReviewStatusData
     */
    public static function fromPeerReviewStatusData(Enumerable $peerReviewDataCollection): self
    {
        $dateStarted = $peerReviewDataCollection
            ->map(fn (PeerReviewStatusData $data) => $data->dateStarted)
            ->filter()
            ->sort()
            ->first();

        $dateInProgress = $peerReviewDataCollection
            ->map(fn (PeerReviewStatusData $data) => $data->dateInProgress)
            ->filter()
            ->sort()
            ->first();
        $isInProgress = $dateInProgress !== null;

        $isNotStarted = $peerReviewDataCollection->every(
            fn (PeerReviewStatusData $data) => $data->status === PublicReviewStatus::NotStarted
        );
        $isComplete = $peerReviewDataCollection->every(
            fn (PeerReviewStatusData $data) => $data->dateCompleted !== null
        );
        $dateCompleted = null;

        if ($isNotStarted) {
            $status = PublicReviewStatus::NotStarted;
        } elseif ($isComplete) {
            $status = PublicReviewStatus::Complete;

            // Most recent date completed
            $dateCompleted = $peerReviewDataCollection
                ->map(fn (PeerReviewStatusData $data) => $data->dateCompleted)
                ->sort()
                ->last();
        } elseif ($isInProgress) {
            $status = PublicReviewStatus::InProgress;
        } else {
            $status = PublicReviewStatus::NotStarted;
        }

        return new self($status, $dateStarted, $dateInProgress, $dateCompleted);
    }

    /**
     * Checks if a minimum review count is configured and the passed review assignments meet it.
     *
     * NB: Returns false when no minimum is configured, e.g. $minimumRequiredCount === Context::REVIEWS_DEFAULT_COUNT (0)
     *
     * @param Enumerable<int, ReviewAssignment> $reviewAssignments
     */
    public static function meetsConfiguredMinimumReviewCount(
        Enumerable $reviewAssignments,
        int $minimumRequiredCount,
        bool $requireEditorConfirmation = false
    ): bool {
        if ($minimumRequiredCount <= Context::REVIEWS_DEFAULT_COUNT) {
            return false;
        }

        return $reviewAssignments
            ->filter(
                fn (ReviewAssignment $reviewAssignment) =>
                     $requireEditorConfirmation
                        ? self::isCompleteAndConfirmed($reviewAssignment)
                        : $reviewAssignment->getDateCompleted() !== null
            )
            ->count() >= $minimumRequiredCount;
    }

    /**
     * Returns whether a review assignment has been both fully submitted by the reviewer
     * and confirmed by an editor.
     */
    public static function isCompleteAndConfirmed(ReviewAssignment $assignment): bool
    {
        return $assignment->getDateCompleted() !== null && $assignment->isConfirmedByEditor();
    }

    /**
     * @return array{status: string, dateStarted: ?string, dateInProgress: ?string, dateCompleted: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'dateStarted' => $this->dateStarted,
            'dateInProgress' => $this->dateInProgress,
            'dateCompleted' => $this->dateCompleted,
        ];
    }
}
