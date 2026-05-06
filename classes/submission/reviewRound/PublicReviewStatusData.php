<?php

/**
 * @file classes/submission/reviewRound/PublicReviewStatusData.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicReviewStatusData
 *
 * @ingroup submission_reviewRound
 *
 * @brief Processes review assignments/round info to produce public review status state and related dates.
 */

namespace PKP\submission\reviewRound;

use Illuminate\Support\Enumerable;
use PKP\submission\reviewRound\enums\PublicReviewStatus;

readonly class PublicReviewStatusData
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
     * Static constructor for building self from review assignments data.
     *
     * @param Enumerable<int, array{declined: bool, cancelled: bool, dateAssigned: ?string, dateConfirmed: ?string, dateCompleted: ?string}> $assignmentsData Required data to calculate from assignments
     */
    public static function fromAssignmentsData(Enumerable $assignmentsData): self
    {
        $eligibleAssignments = $assignmentsData->filter(
            fn (array $reviewAssignment) => !$reviewAssignment['declined'] && !$reviewAssignment['cancelled']
        );

        // Oldest date assigned
        $dateStarted = $assignmentsData
            ->map(fn (array $reviewAssignment) => $reviewAssignment['dateAssigned'])
            ->filter()
            ->sort()
            ->first();

        // Oldest date confirmed
        $dateInProgress = $eligibleAssignments
            ->map(fn (array $reviewAssignment) => $reviewAssignment['dateConfirmed'])
            ->filter()
            ->sort()
            ->first();

        $isNotStarted = $eligibleAssignments->isEmpty();
        $isComplete = $eligibleAssignments->every(fn (array $reviewAssignment) => $reviewAssignment['dateCompleted'] !== null);
        $isInProgress = $dateInProgress !== null;

        $dateCompleted = null;

        if ($isNotStarted) {
            $status = PublicReviewStatus::NotStarted;
        } elseif ($isComplete) {
            $status = PublicReviewStatus::Complete;

            // Most recent date completed
            $dateCompleted = $eligibleAssignments
                ->map(fn (array $reviewAssignment) => $reviewAssignment['dateCompleted'])
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
     * @param Enumerable<int, array{status: string, dateStarted: ?string, dateInProgress: ?string, dateCompleted: ?string}> $roundsData Required review round data to construct self
     */
    public static function fromRoundsData(Enumerable $roundsData): self
    {
        $dateStarted = $roundsData
            ->pluck('dateStarted')
            ->filter()
            ->sort()
            ->first();

        $dateInProgress = $roundsData
            ->pluck('dateInProgress')
            ->filter()
            ->sort()
            ->first();

        $isNotStarted = $roundsData->every(function (array $roundData) {
            $publicReviewStatus = PublicReviewStatus::tryFrom($roundData['status']);
            // In cases where the backed enum cannot be created for whatever reason,
            // assume something's missing, and therefore review hasn't started yet.
            if ($publicReviewStatus === null) {
                return true;
            }

            return $publicReviewStatus->value === PublicReviewStatus::NotStarted->value;
        });
        $isComplete = $roundsData->every(fn (array $roundData) => $roundData['dateCompleted'] !== null);
        $isInProgress = $dateInProgress !== null;

        $dateCompleted = null;

        if ($isNotStarted) {
            $status = PublicReviewStatus::NotStarted;
        } elseif ($isComplete) {
            $status = PublicReviewStatus::Complete;

            // Most recent date completed
            $dateCompleted = $roundsData
                ->map(fn (array $roundData) => $roundData['dateCompleted'])
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
