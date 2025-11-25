<?php

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

class PublicationPeerReviewSummaryResource extends JsonResource
{
    public function toArray(?\Illuminate\Http\Request $request = null)
    {
        $reviewsGroupedByRoundId = Repo::reviewAssignment()->getCollector()
            ->filterByPublicationId($this->getId())
            ->getMany()
            ->groupBy(fn (ReviewAssignment $reviewAssignment, int $key) => $reviewAssignment->getReviewRoundId())
            // For each round, filter out reviews that has no response from reviewer. This way, a reviewer's last response will be the one reflected in final summary count
            ->map(function ($assignments) {
                return $assignments->filter(fn (ReviewAssignment $ra) => !!$ra->getDateCompleted());
            })
            ->sortKeys();

        $reviewerResponseCount = collect();
        foreach ($reviewsGroupedByRoundId as $key => $reviews) {
            /** @var ReviewAssignment $review */
            foreach ($reviews as $review) {
                // For each review in each round, record the reviewer's decision, overriding any decision from previous rounds
                $reviewerResponseCount->put($review->getReviewerId(), $review->getReviewerRecommendationId());
            }
        }

        $reviewerResponseCount = $reviewerResponseCount->countBy();
        $summaryCount = [];

        foreach (ReviewerRecommendation::all() as $recommendationType) {
            $count = $reviewerResponseCount->get($recommendationType->id, 0);
            $summaryCount[] = [
                'recommendationTypeId' => $recommendationType->id,
                'recommendationTyeText' => $recommendationType->getLocalizedData('title'),
                'count' => $count,
            ];
        }

        $submission = Repo::submission()->get($this->getData('submissionId'));
        $currentPublication = $submission->getCurrentPublication();
        $publishedPublications = $submission->getPublishedPublications();

        return [
            'publicationId' => $this->getId(),
            'versionString' => $this->getData('versionString'),
            'summaryCount' => $summaryCount,
            // Number of published versions of the publication's submission
            'publishedVersions' => count($publishedPublications),
            // Latest published publication for the submission associated with this publication
            'currentVersion' => $currentPublication ? [
                'title' => $currentPublication->getLocalizedTitle(),
                'datePublished' => $currentPublication->getData('datePublished'),
            ] : null
        ];
    }
}
