<?php

/**
 * @file api/v1/peerReviews/resources/PublicationPeerReviewSummaryResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class PublicationPeerReviewSummaryResource
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Resource that maps a publication to a summary of its peer reviews.
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\context\Context;

class PublicationPeerReviewSummaryResource extends JsonResource
{
    use ReviewerRecommendationSummary;

    public function toArray(\Illuminate\Http\Request $request)
    {
        /** @var Publication $publication */
        $publication = $this->resource;

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $contextDao = Application::getContextDAO();
        /** @var Context $context */
        $context = $contextDao->getById($submission->getData('contextId'));

        $allAssociatedPublicationIds = Repo::publication()->getWithSourcePublicationsIds([$publication->getId()])->all();

        // Include reviews from the Publication's Source Publication so that reviews that are to be copied forward are accounted for.
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByIsPubliclyVisible(true)
            ->filterByPublicationIds($allAssociatedPublicationIds)
            ->getMany();

        $publishedPublications = $submission->getPublishedPublications();

        return [
            'publicationId' => $publication->getId(),
            'reviewerRecommendations' => $this->getReviewerRecommendationsSummary($reviewAssignments, $context),
            // Number of published versions of the publication's submission
            'submissionPublishedVersionsCount' => count($publishedPublications),
            'reviewerCount' => $this->getReviewerCount($reviewAssignments),
            // Latest published publication for the submission associated with this publication
            'submissionCurrentVersion' => $this->getSubmissionLatestPublishedPublication($submission),
        ];
    }
}
