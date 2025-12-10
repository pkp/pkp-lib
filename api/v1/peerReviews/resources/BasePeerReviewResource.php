<?php

/**
 * @file api/v1/peerReviews/resources/BasePeerReviewResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class BasePeerReviewResource
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief A base class for API resource classes related to public peer reviews
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

class BasePeerReviewResource extends JsonResource
{
    /**
     * Aggregates reviewer recommendations into summary counts.
     *  - If a reviewer participates in multiple rounds, only their latest completed review counts
     *  - Incomplete reviews (no completion date) are excluded
     *
     * @param Enumerable $reviewAssignments The Review Assignments to create summary from.
     */
    public function getReviewerRecommendationsSummary(Enumerable $reviewAssignments, Context $context): array
    {
        $reviewAssignmentsGroupedByRoundId = $reviewAssignments
            ->groupBy(fn (ReviewAssignment $ra) => $ra->getReviewRoundId())
            ->map(
                fn ($assignments) =>
                $assignments->filter(fn (ReviewAssignment $ra) => !!$ra->getDateCompleted())
            )
            ->sortKeys();

        return $this->getSummaryCountForReviewerRecommendation($reviewAssignmentsGroupedByRoundId, $context);
    }

    /**
     * Get the summary count for reviews.
     */
    private function getSummaryCountForReviewerRecommendation(Enumerable $reviewAssignmentsGroupedByRoundId, Context $context): array
    {
        $responses = collect();

        $availableRecommendationsGroupedByType = ReviewerRecommendation::withContextId($context->getId())->get();

        foreach ($reviewAssignmentsGroupedByRoundId as $reviews) {
            /** @var ReviewAssignment $review */
            foreach ($reviews as $review) {
                // For each review in each round, record the reviewer's decision, overriding any decision from previous rounds, keeping their latest recommendation
                $responses->put(
                    $review->getReviewerId(),
                    $availableRecommendationsGroupedByType->get($review->getReviewerRecommendationId())->type,
                );
            }
        }

        return $this->buildSummaryCount($responses->countBy(), $context);
    }

    /**
     * Tally review recommendations for each Recommendation type
     */
    private function buildSummaryCount(Enumerable $reviewerResponseCount, Context $context): array
    {
        $summary = [];

        $recTypes = ReviewerRecommendation::withContextId($context->getId())
            ->get()
            ->groupBy('type');
        $recommendationTypeLabels = Repo::reviewerRecommendation()->getRecommendationTypeLabels();
        foreach ($recTypes as $typeId => $recommendation) {
            $summary[] = [
                'recommendationTypeId' => $typeId,
                'recommendationTypeLabel' => $recommendationTypeLabels[$typeId],
                'count' => $reviewerResponseCount->get($typeId, 0),
            ];
        }
        return $summary;
    }
}
