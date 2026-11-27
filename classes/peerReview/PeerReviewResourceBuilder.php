<?php

/**
 * @file classes/peerReview/PeerReviewResourceBuilder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PeerReviewResourceBuilder
 *
 * @brief Builder class for constructing peer review related API resource classes.
 * NB: All instances of `SubmissionPeerReviewResource` and `SubmissionPeerReviewSummaryResource`
 * should be instantiated using this builder.
 */

namespace PKP\peerReview;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewResource;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewRound\PublicReviewStatusData;

readonly class PeerReviewResourceBuilder
{
    /** @var Collection<int, Submission> $submissions */
    private Collection $submissions;
    private int $contextId;
    private bool $isSingleSubmission;

    private function __construct(Enumerable $submissions, int $contextId, bool $isSingleSubmission = false)
    {
        $this->submissions = $submissions->collect();
        $this->contextId = $contextId;
        $this->isSingleSubmission = $isSingleSubmission;
    }

    /**
     * Build resources from a collection of submissions.
     *
     * NB: All submission are assumed to belong to the contextId provided.
     *
     * @param Enumerable<int, Submission> $submissions
     */
    public static function fromSubmissions(Enumerable $submissions, int $contextId): self
    {
        return new self($submissions, $contextId);
    }

    /**
     * Build resource from a single submission.
     */
    public static function fromSubmission(Submission $submission): self
    {
        return new self(collect([$submission]), $submission->getData('contextId'), true);
    }

    /**
     * Build resource from an array of submissionIds.
     *
     * NB: All submissionIds are assumed to belong to the contextId provided.
     */
    public static function fromSubmissionIds(array $submissionIds, int $contextId): self
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->filterByContextIds([$contextId])
            ->getMany();
        return new self($submissions, $contextId);
    }

    /**
     * Create an instance of a single SubmissionPeerReviewResource
     *
     * NB: Will throw a warning if more than one submission or submissionId was provided via an above method.
     */
    public function make(): SubmissionPeerReviewResource
    {
        if (!$this->isSingleSubmission) {
            throw new \Exception('`make()` can only be run when a single submission is provided to the builder');
        }
        $submission = $this->submissions->first();
        return new SubmissionPeerReviewResource($submission);
    }

    /**
     * Create an `AnonymousResourceCollection` of `SubmissionPeerReviewResource`s`
     */
    public function makeCollection(): AnonymousResourceCollection
    {
        return SubmissionPeerReviewResource::collection($this->submissions);
    }

    /**
     * Create an instance of a single SubmissionPeerReviewSummaryResource
     *
     * NB: Will throw a warning if more than one submission or submissionId was provided via an above method.
     */
    public function makeSummary(): SubmissionPeerReviewSummaryResource
    {
        if (!$this->isSingleSubmission) {
            throw new \Exception('`makeSummary()` can only be run when a single submission is provided to the builder');
        }
        $summaryData = $this->buildSummaryData()->first();
        return new SubmissionPeerReviewSummaryResource($summaryData);
    }

    /**
     * Create an `AnonymousResourceCollection` of `SubmissionPeerReviewSummaryResource`s`
     */
    public function makeSummaryCollection(): AnonymousResourceCollection
    {
        return SubmissionPeerReviewSummaryResource::collection($this->buildSummaryData());
    }


    /**
     * Get the public review status of each submission (not started, in progress or complete),
     * as shown to readers in the summary resource. Used e.g. to index the status for search.
     *
     * @return Collection<int, PublicReviewStatusData>
     */
    public function getReviewStatuses(): Collection
    {
        $acceptedReviewAssignments = $this->getAcceptedReviewAssignments();

        return $this->submissions->mapWithKeys(fn (Submission $submission) => [
            $submission->getId() => PublicReviewStatusData::fromSubmissionReviewAssignments(
                $acceptedReviewAssignments->get($submission->getId(), collect())
            ),
        ]);
    }

    /**
     * Constructs the data transfer objects used to construct sSubmissionPeerReviewSummaryResource`s.
     *
     * Runs all necessary DB queries one for all submissions/submissionIds to avoid n+1 issues.
     *
     * @return Collection<int, SubmissionReviewData> keyed by submission ID
     */
    private function buildSummaryData(): Collection
    {
        $acceptedReviewAssignments = $this->getAcceptedReviewAssignments();
        $recommendationTypes = ReviewerRecommendation::withContextId($this->contextId)
            ->get()
            ->keyBy('reviewerRecommendationId');
        return $this->submissions
            ->keyBy(fn (Submission $submission) => $submission->getId())
            ->map(function (Submission $submission, int $submissionId) use ($acceptedReviewAssignments, $recommendationTypes) {
                $reviewAssignments = $acceptedReviewAssignments->get($submissionId, collect());
                return new SubmissionReviewData($submission, $reviewAssignments, $recommendationTypes);
            });
    }

    /**
     * Returns collection of published publication IDs by submission ID
     *
     * @return Collection<int, int[]> key: submission ID; value published publication IDs
     */
    private function getPublishedPublicationIds(): Collection
    {
        return $this->submissions
            ->mapWithKeys(function (Submission $submission) {
                $publishedPublicationIds = Arr::map(
                    $submission->getPublishedPublications(),
                    fn (Publication $publication) => $publication->getId()
                );

                return [$submission->getId() => $publishedPublicationIds];
            });
    }

    /**
     * Get all accepted review assignments keyed by submission ID.
     * Confirmed ones will be filtered and exposed to peer review API, while all accepted reviews will be considered for summary.
     *
     * @return Collection<int, Collection<int, ReviewAssignment>> [submissionId => [reviewAssignmentId => reviewAssignment]]
     */
    private function getAcceptedReviewAssignments(): Collection
    {
        $allPublishedPublicationIds = $this->getPublishedPublicationIds()
            ->flatten()
            ->toArray();

        return Repo::reviewAssignment()
            ->getCollector()
            ->filterByPublicationIds($allPublishedPublicationIds)
            ->filterByIsPubliclyVisible(true)
            ->filterByIsAccepted(true)
            ->getMany()
            // Materialize the lazy collection: it is iterated once per consumer
            // below and each LazyCollection iteration re-runs the query and
            // re-hydrates every assignment
            ->collect()
            ->groupBy(
                fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getSubmissionId(),
                preserveKeys: true
            );

    }
}
