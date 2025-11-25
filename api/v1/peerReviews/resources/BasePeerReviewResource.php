<?php

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

class BasePeerReviewResource extends JsonResource
{
    public function getReviewerRecommendationsSummary(Enumerable $reviewAssignments): array
    {
        $reviewAssignmentsGroupedByRoundId = $reviewAssignments
            ->groupBy(fn (ReviewAssignment $ra) => $ra->getReviewRoundId())
            ->map(
                fn ($assignments) =>
                $assignments->filter(fn (ReviewAssignment $ra) => !!$ra->getDateCompleted())
            )
            ->sortKeys();

        return $this->getSummaryCountForReviewerRecommendation($reviewAssignmentsGroupedByRoundId);
    }

    private function getSummaryCountForReviewerRecommendation(Enumerable $reviewAssignmentsGroupedByRoundId): array
    {
        $responses = collect();

        $availableRecommendationsGroupedByType = ReviewerRecommendation::withContextId(1)->get();

        foreach ($reviewAssignmentsGroupedByRoundId as $reviews) {
            /** @var ReviewAssignment $review */
            foreach ($reviews as $review) {
                // For each review in each round, record the reviewer's decision, overriding any decision from previous rounds
                // Therefore keeping their latest recommendation
                $responses->put(
                    $review->getReviewerId(),
                    $availableRecommendationsGroupedByType->get($review->getReviewerRecommendationId())->recommendationType,
                );
            }
        }

        return $this->buildSummaryCount($responses->countBy());
    }

    private function buildSummaryCount(Enumerable $reviewerResponseCount): array
    {
        $summary = [];

        foreach (ReviewerRecommendation::all() as $type) {
            $summary[] = [
                'recommendationTypeId' => $type->id,
                'recommendationTypeText' => $type->getLocalizedData('title'),
                'count' => $reviewerResponseCount->get($type->id, 0),
            ];
        }

        $summary = [];

        // Group by type
        $recTypes = ReviewerRecommendation::withContextId(1)->get()->groupBy('type');
        $recommendationTypeLabels = Repo::reviewerRecommendation()->getRecommendationTypeLabels();
        // go through each group
        foreach ($recTypes as $typeId => $recommendation) {
            $summary[] = [
                'recommendationTypeId' => $typeId,
                'recommendationTypeLabel' => $recommendationTypeLabels[$typeId],
                'count' => $reviewerResponseCount->get($typeId, 0),
            ];
        }
        // get count and locale for each
        return $summary;
    }
}
