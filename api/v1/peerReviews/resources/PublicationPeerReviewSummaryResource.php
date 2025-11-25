<?php

namespace PKP\API\v1\peerReviews\resources;

use APP\facades\Repo;

class PublicationPeerReviewSummaryResource extends BasePeerReviewResource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $allAssociatedPublicationIds = Repo::publication()->getWithSourcePublicationsIds([$this->getId()])->all();

        // Include reviews from the Publication's Source Publication so that are are to be copied forward are accounted for.
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByPublicationIds($allAssociatedPublicationIds)
            ->getMany();

        $submission = Repo::submission()->get($this->getData('submissionId'));
        $currentPublication = $submission->getCurrentPublication();
        $publishedPublications = $submission->getPublishedPublications();

        return [
            'publicationId' => $this->getId(),
            'reviewerRecommendations' => $this->getReviewerRecommendationsSummary($reviewAssignments),
            // Number of published versions of the publication's submission
            'submissionPublishedVersionsCount' => count($publishedPublications),
            // Latest published publication for the submission associated with this publication
            'submissionCurrentVersion' => $currentPublication ? [
                'title' => $currentPublication->getLocalizedTitle(),
                'datePublished' => $currentPublication->getData('datePublished'),
            ] : null
        ];
    }
}
