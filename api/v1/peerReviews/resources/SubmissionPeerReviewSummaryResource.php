<?php

namespace pkp\api\v1\peerReviews\resources;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

class SubmissionPeerReviewSummaryResource extends JsonResource
{
    public function toArray(Request $request)
    {
        /** @var Submission $submission */
        $submission = $this->resource;

        $reviewsGrouped = $this->getCompletedReviewsGroupedByRound($submission);
        $reviewerResponseCount = $this->getLatestReviewerResponses($reviewsGrouped);
        $summaryCount = $this->buildSummaryCount($reviewerResponseCount);

        return [
            'submissionId' => $submission->getId(),
            'summaryCount' => $summaryCount,
            'publishedVersions' => count($submission->getPublishedPublications()),
            'currentVersion' => $this->getCurrentVersionData($submission),
        ];
    }

    private function getCompletedReviewsGroupedByRound(Submission $submission)
    {
        return Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany()
            ->groupBy(fn (ReviewAssignment $ra) => $ra->getReviewRoundId())
            ->map(
                fn ($assignments) =>
            $assignments->filter(fn (ReviewAssignment $ra) => !!$ra->getDateCompleted())
            )
            ->sortKeys();
    }

    private function getLatestReviewerResponses($reviewsGrouped)
    {
        $responses = collect();

        foreach ($reviewsGrouped as $reviews) {
            foreach ($reviews as $review) {
                $responses->put(
                    $review->getReviewerId(),
                    $review->getReviewerRecommendationId()
                );
            }
        }

        return $responses->countBy();
    }
    private function buildSummaryCount(Enumerable $reviewerResponseCount)
    {
        $summary = [];

        foreach (ReviewerRecommendation::all() as $type) {
            $summary[] = [
                'recommendationTypeId' => $type->id,
                'recommendationTypeText' => $type->getLocalizedData('title'),
                'count' => $reviewerResponseCount->get($type->id, 0),
            ];
        }

        return $summary;
    }
    private function getCurrentVersionData(Submission $submission)
    {
        $publication = $submission->getCurrentPublication();

        if (!$publication) {
            return null;
        }

        return [
            'title' => $publication->getLocalizedTitle(),
            'datePublished' => $publication->getData('datePublished'),
        ];
    }
}
