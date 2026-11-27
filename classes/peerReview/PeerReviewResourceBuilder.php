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
use APP\publication\enums\VersionStage;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewResource;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

readonly class PeerReviewResourceBuilder
{
    /** @var Collection<int, Submission> $submissions */
    private Collection $submissions;
    private Context $context;
    private bool $isSingleSubmission;

    private function __construct(Enumerable $submissions, Context $context, bool $isSingleSubmission = false)
    {
        $this->submissions = $submissions->collect();
        $this->context = $context;
        $this->isSingleSubmission = $isSingleSubmission;
    }

    /**
     * Build resources from a collection of submissions.
     *
     * NB: All submission are assumed to belong to the contextId provided.
     *
     * @param Enumerable<int, Submission> $submissions
     */
    public static function fromSubmissions(Enumerable $submissions, Context $context): self
    {
        return new self($submissions, $context);
    }

    /**
     * Build resource from a single submission.
     */
    public static function fromSubmission(Submission $submission, Context $context): self
    {
        return new self(collect([$submission]), $context, true);
    }

    /**
     * Build resource from an array of submissionIds.
     *
     * NB: All submissionIds are assumed to belong to the contextId provided.
     */
    public static function fromSubmissionIds(array $submissionIds, Context $context): self
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->filterByContextIds([$context->getId()])
            ->getMany()
            ->collect();

        $isSingleSubmission = count($submissionIds) === 1;
        return new self($submissions, $context, $isSingleSubmission);
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
        // FIXME: SubmissionPeerReviewResource still takes a Submission directly as its resource.
        //  This means, e.g. `SubmissionPeerReviewResource::toArray()` will make a DB call to instantiate the context
        //  for every iteration. If performance with the below collection becomes a problem, optimization should
        //  start there.
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
     * Constructs the data transfer objects used to construct sSubmissionPeerReviewSummaryResource`s.
     *
     * Runs all necessary DB queries one for all submissions/submissionIds to avoid n+1 issues.
     *
     * @return Collection<int, SubmissionReviewData> keyed by submission ID
     */
    private function buildSummaryData(): Collection
    {
        $acceptedReviewAssignments = $this->getAcceptedReviewAssignments();
        $recommendationTypes = ReviewerRecommendation::withContextId($this->context->getId())
            ->get()
            ->keyBy('reviewerRecommendationId');
        $pmurReviewRoundIds = $this->getPmurReviewRoundIds();
        return $this->submissions
            ->keyBy(fn (Submission $submission) => $submission->getId())
            ->map(function (Submission $submission, int $submissionId) use ($acceptedReviewAssignments, $recommendationTypes, $pmurReviewRoundIds) {
                $reviewAssignments = $acceptedReviewAssignments->get($submissionId, collect());
                return new SubmissionReviewData(
                    $submission,
                    $this->context,
                    $reviewAssignments,
                    $recommendationTypes,
                    $pmurReviewRoundIds->get($submissionId, [])
                );
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

    /**
     * Review round IDs associated with a PMUR
     * Used when considering a newly started PMUR review round as part of the review summary
     *
     * @return Collection<int, int[]> Array of review round IDs keyed by submission ID
     */
    private function getPmurReviewRoundIds(): Collection
    {
        // `tryFrom` since OPS has no PMUR
        $pmurStage = VersionStage::tryFrom('PMUR');
        if ($pmurStage === null) {
            return collect();
        }

        $pmurPublicationIds = $this->submissions
            ->flatMap(fn (Submission $submission) => collect($submission->getData('publications') ?? [])->all())
            ->filter(fn (Publication $publication) => $publication->getVersion()?->stage === $pmurStage)
            ->map(fn (Publication $publication) => $publication->getId())
            ->values()
            ->all();

        if (empty($pmurPublicationIds)) {
            return collect();
        }

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = collect($reviewRoundDao->getByPublicationIds($pmurPublicationIds)->toIterator());

        return $reviewRounds
            ->groupBy(fn (ReviewRound $round) => $round->getSubmissionId())
            ->map(fn (Collection $rounds) => $rounds->map(fn (ReviewRound $round) => $round->getId())->all());
    }
}
