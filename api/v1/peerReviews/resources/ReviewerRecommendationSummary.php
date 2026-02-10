<?php

/**
 * @file api/v1/peerReviews/resources/ReviewerRecommendationSummary.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class ReviewerRecommendationSummary
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Trait for summarizing reviewer recommendations.
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

trait ReviewerRecommendationSummary
{
    /**
     * Aggregates reviewer recommendations into summary counts.
     *  - If a reviewer participates in multiple rounds, only their latest completed review counts
     *  - Incomplete reviews (no completion date) are excluded
     *
     * @param Enumerable $reviewAssignments The Review Assignments to create summary from.
     */
    private function getReviewerRecommendationsSummary(Enumerable $reviewAssignments, Context $context): array
    {
        $reviewAssignmentsGroupedByRoundId = $reviewAssignments
            ->groupBy(fn (ReviewAssignment $ra) => $ra->getReviewRoundId())
            ->map(
                fn ($assignments) => $assignments->filter(fn (ReviewAssignment $ra) => !!$ra->getDateCompleted())
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

        $availableRecommendationTypes = ReviewerRecommendation::withContextId($context->getId())->get();

        foreach ($reviewAssignmentsGroupedByRoundId as $reviews) {
            /** @var ReviewAssignment $review */
            foreach ($reviews as $review) {
                // For each review in each round, record the reviewer's decision, overriding any decision from previous rounds, keeping their latest recommendation
                $responses->put(
                    $review->getReviewerId(),
                    $availableRecommendationTypes->get($review->getReviewerRecommendationId())->type,
                );
            }
        }

        return $this->buildSummaryCount($responses->countBy(), $availableRecommendationTypes);
    }

    /**
     * Tally review recommendations for each Recommendation type
     */
    private function buildSummaryCount(Enumerable $reviewerResponseCount, $recommendationTypes): array
    {
        $summary = [];
        $recommendationTypes = $recommendationTypes->groupBy('type');
        $recommendationTypeLabels = Repo::reviewerRecommendation()->getRecommendationTypeLabels();

        foreach ($recommendationTypes as $typeId => $recommendation) {
            $summary[] = [
                'recommendationTypeId' => $typeId,
                'recommendationTypeLabel' => $recommendationTypeLabels[$typeId],
                'count' => $reviewerResponseCount->get($typeId, 0),
            ];
        }
        return $summary;
    }

    /**
     * Get count of reviewers who have contributed to reviews.
     *
     * @param Enumerable $reviewAssignments - List of review assignments to generate count from.
     */
    private function getReviewerCount(Enumerable $reviewAssignments)
    {
        $reviewerIds = $reviewAssignments
            ->filter(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getDateCompleted() !== null)
            ->map(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getReviewerId())
            ->all();

        return count(array_unique($reviewerIds));
    }

    /**
     * Get info for the latest published publication for a submission.
     * @return array{'versionString': string, 'datePublished': string}|null
     * - versionString: The version string of the latest published publication.
     * - datePublished: The publication date as a string.
     */
    private function getSubmissionLatestPublishedPublication(Submission $submission): ?array
    {
        /** @var Publication $latestVersion */
        $latestVersion = $submission->getData('publications')
            ->filter(fn(Publication $publication) => $publication->getData('status') === Submission::STATUS_PUBLISHED)
            ->sortByDesc(fn(Publication $publication) => $publication->getData('version'))
            ->first();

        return $latestVersion ? [
            'versionString' => $latestVersion->getData('versionString'),
            'datePublished' => $latestVersion->getData('datePublished'),
        ] : null;
    }
}
