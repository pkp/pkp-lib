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
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\enums\PublicReviewStatus;
use PKP\submission\reviewRound\ReviewRoundDAO;

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
            'reviewStatus' => $this->getReviewStatus($reviewAssignments),
        ];
    }

    /**
     * Gets aggregated review round status for submission as a whole.
     *
     * @param Collection<ReviewAssignment> $reviewAssignments
     * @return array{dateStarted: ?string, dateInProgress: ?string, dateCompleted: ?string}
     */
    private function getReviewStatus(Enumerable $reviewAssignments): array
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDao->getBySubmissionId($this->resource->getId())->toAssociativeArray();


        $roundsStatusData = $this->getReviewRoundsStatusData($reviewAssignments, $reviewRounds);

        if (empty($roundsStatusData)) {
            return [
                'dateStarted' => null,
                'dateInProgress' => null,
                'dateCompleted' => null,
            ];
        }

        $lastRound = end($roundsStatusData);

        $dateStarted = collect($roundsStatusData)
            ->pluck('dateStarted')
            ->filter()
            ->sort()
            ->first();

        $dateInProgress = collect($roundsStatusData)
            ->pluck('dateInProgress')
            ->filter()
            ->sort()
            ->first();

        return [
            'dateStarted' => $dateStarted,
            'dateInProgress' => $dateInProgress,
            'dateCompleted' => $lastRound['dateCompleted'],
        ];
    }
}
