<?php

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Http\Request;

class SubmissionPeerReviewSummaryResource extends BasePeerReviewResource
{
    public function toArray(Request $request)
    {
        /** @var Submission $submission */
        $submission = $this->resource;
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany();

        $currentPublication = $submission->getCurrentPublication();
        return [
            'submissionId' => $submission->getId(),
            'reviewerRecommendations' => $this->getReviewerRecommendationsSummary($reviewAssignments),
            'publishedVersionsCount' => count($submission->getPublishedPublications()),
            'currentVersion' => $currentPublication ? [
                'title' => $currentPublication->getLocalizedTitle(),
                'datePublished' => $currentPublication->getData('datePublished'),
            ] : null,
        ];
    }
}
