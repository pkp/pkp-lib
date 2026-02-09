<?php

/**
 * @file classes/submission/reviewRound/Repository.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief ReviewRound Repository
 */

namespace PKP\submission\reviewRound;

class Repository
{
    /**
     * Get the last review round for a submission, optionally filtered by stage.
     */
    public function getLastReviewRoundBySubmissionId(int $submissionId, ?int $stageId = null): ?ReviewRound
    {
        $query = ReviewRound::where('submission_id', $submissionId);

        if ($stageId !== null) {
            $query->where('stage_id', $stageId);
        }

        return $query
            ->orderByDesc('stage_id')
            ->orderByDesc('round')
            ->first();
    }

    /**
     * Fetch a review round, creating it if needed.
     *
     * @param int $stageId One of the WORKFLOW_*_REVIEW_STAGE_ID constants.
     * @param ?int $status One of the ReviewRound::REVIEW_ROUND_STATUS_* constants.
     *
     */
    public function build(int $submissionId, int $publicationId, int $stageId, int $round, ?int $status = null): ?ReviewRound
    {
        // If one exists, fetch and return.
        $reviewRound = ReviewRound::withSubmissionIds([$submissionId])
            ->withStageId($stageId)
            ->withRound($round)
            ->first();

        if ($reviewRound) {
            return $reviewRound;
        }

        // Otherwise, check the args to build one.
        if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ||
            $stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW &&
            $round > 0
        ) {
            return ReviewRound::create([
                'submissionId' => $submissionId,
                'publicationId' => $publicationId,
                'round' => $round,
                'stageId' => $stageId,
                'status' => $status
            ]);
        } else {
            return null;
        }
    }

}
