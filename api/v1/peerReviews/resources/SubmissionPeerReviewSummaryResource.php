<?php

/**
 * @file api/v1/peerReviews/resources/SubmissionPeerReviewSummaryResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class SubmissionPeerReviewSummaryResource
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Resource that maps a submission to a summary of its peer reviews
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\submission\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\peerReview\SubmissionReviewData;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\PublicReviewStatusData;
use PKP\submission\reviewRound\ReviewRound;

class SubmissionPeerReviewSummaryResource extends JsonResource
{
    use ReviewerRecommendationSummary;

    public function toArray(Request $request)
    {
        /** @var SubmissionReviewData $resource */
        $resource = $this->resource;

        return [
            'submissionId' => $resource->submission->getId(),
            'reviewerRecommendations' => $this->getReviewerRecommendationsSummary($resource->reviewAssignments, $resource->recommendationTypes),
            'submissionPublishedVersionsCount' => count($resource->submission->getPublishedPublications()),
            'reviewerCount' => $this->getReviewerCount($resource->reviewAssignments),
            'submissionCurrentVersion' => $this->getSubmissionLatestPublishedPublication($resource->submission),
            'reviewStatus' => $this->getReviewStatus($resource->reviewAssignments),
        ];
    }

    /**
     * Gets aggregated review round status for submission as a whole.
     * Rounds are derived from assignments not ReviewRound objects.
     * Rounds with no public, accepted assignments do not affect the status.
     *
     * @param Collection<ReviewAssignment> $reviewAssignments
     *
     * @return array{dateStarted: ?string, dateInProgress: ?string, dateCompleted: ?string}
     */
    private function getReviewStatus(Enumerable $reviewAssignments): array
    {
        return PublicReviewStatusData::fromSubmissionReviewAssignments($reviewAssignments)->toArray();
    }
}
