<?php

/**
 * @file api/v1/peerReviews/resources/SubmissionPeerReviewDataCache.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class SubmissionPeerReviewDataCache
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Data container for caching shared submission peer review data for API resources
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

readonly class SubmissionPeerReviewDataCache
{
    public Context $context;

    /** @var Collection<int, ReviewRound> $publicReviewRounds */
    public Collection $publicReviewRounds;

    /** @var Collection<int, ReviewAssignment> $publicAcceptedReviewAssignments  */
    public Collection $publicAcceptedReviewAssignments;

    /** @var Collection<int, ReviewerRecommendation> $availableRecommendationTypes  */
    public Collection $availableRecommendationTypes;

    public function __construct(
        private Submission $submission
    ) {
        $this->context = Application::getContextDAO()->getById($this->submission->getData('contextId'));
        $this->publicReviewRounds = $this->getPublicReviewRounds($this->submission);

        $roundIds = $this->publicReviewRounds->keys()->all();
        $this->publicAcceptedReviewAssignments = empty($roundIds) ? collect() : Repo::reviewAssignment()
            ->getCollector()
            ->filterByReviewRoundIds($roundIds)
            ->filterByIsPubliclyVisible(true)
            ->filterByIsAccepted(true)
            ->getMany()
            // Each LazyCollection iteration re-runs the query and re-hydrates every assignment
            // so we materialize it here.
            ->collect();

        $this->availableRecommendationTypes = ReviewerRecommendation::withContextId($this->context->getId())
            ->get()
            ->keyBy('reviewerRecommendationId');
    }

    /**
     * Creates a new instance of SubmissionPeerReviewResource with a hydrated copy of this data object
     */
    public function createSubmissionPeerReviewResource(): SubmissionPeerReviewResource
    {
        return (new SubmissionPeerReviewResource($this->submission))->useDataCache($this);
    }

    /**
     * Creates a new instance of SubmissionPeerReviewSummaryResource with a hydrated copy of this data object
     */
    public function createSubmissionPeerReviewSummaryResource(): SubmissionPeerReviewSummaryResource
    {
        return (new SubmissionPeerReviewSummaryResource($this->submission))->useDataCache($this);
    }

    /**
     * Get the review rounds that are part of the public peer review record.
     * Only rounds whose reviewed publication version is published are included;
     * rounds of an unpublished (in-review) version stay hidden.
     *
     * @return Collection<int, ReviewRound> Review rounds keyed by review round ID
     */
    private function getPublicReviewRounds(Submission $submission): Collection
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        $publishedPublicationIds = collect($submission->getPublishedPublications())
            ->map(fn (Publication $publication) => $publication->getId());

        return collect($reviewRoundDao->getBySubmissionId($submission->getId())->toAssociativeArray())
            ->filter(fn (ReviewRound $reviewRound) => $reviewRound->getPublicationId() !== null
                && $publishedPublicationIds->contains((int) $reviewRound->getPublicationId()));
    }
}
