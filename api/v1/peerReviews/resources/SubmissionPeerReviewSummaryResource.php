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

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\context\Context;

class SubmissionPeerReviewSummaryResource extends JsonResource
{
    use ReviewerRecommendationSummary;

    public function toArray(Request $request)
    {
        /** @var Submission $submission */
        $submission = $this->resource;
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByIsPubliclyVisible(true)
            ->filterByIsAccepted(true)
            ->getMany();

        $contextDao = Application::getContextDAO();
        /** @var Context $context */
        $context = $contextDao->getById($submission->getData('contextId'));

        return [
            'submissionId' => $submission->getId(),
            'reviewerRecommendations' => $this->getReviewerRecommendationsSummary($reviewAssignments, $context),
            'submissionPublishedVersionsCount' => count($submission->getPublishedPublications()),
            'reviewerCount' => $this->getReviewerCount($reviewAssignments),
            'submissionCurrentVersion' => $this->getSubmissionLatestPublishedPublication($submission),
        ];
    }
}
