<?php

namespace PKP\peerReview;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewResourceBuilder
{
    /** @var Collection<int, Submission> $submissions */
    protected Collection $submissions;
    protected int $contextId;

    private function __construct(Enumerable $submissions, int $contextId)
    {
        $this->submissions = $submissions->collect();
        $this->contextId = $contextId;
    }

    public static function fromSubmissions(Enumerable $submissions, int $contextId): self
    {
        return new self($submissions, $contextId);
    }

    public static function fromSubmission(Submission $submission): self
    {
        return new self(collect([$submission]), $submission->getData('contextId'));
    }

    public static function fromSubmissionIds(array $submissionIds, int $contextId): self
    {
        $submissions = Repo::submission()
            ->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->filterByContextIds([$contextId])
            ->getMany();
        return new self($submissions, $contextId);
    }

    public function makeSummaryCollection(): AnonymousResourceCollection
    {
        return SubmissionPeerReviewSummaryResource::collection($this->buildSummaryData());
    }

    public function makeSummary(): ?SubmissionPeerReviewSummaryResource
    {
        // PR_TODO: Determine how to handle when there's more than one submission present
        $summaryData = $this->buildSummaryData()->first();
        return $summaryData ? new SubmissionPeerReviewSummaryResource($summaryData) : null;
    }


    /**
     * @return Collection<int, SubmissionReviewData> keyed by submission ID
     */
    private function buildSummaryData(): Collection
    {
        $acceptedReviewAssignments = $this->getAcceptedReviewAssignments();
        return $this->submissions
            ->keyBy(fn (Submission $submission) => $submission->getId())
            ->map(function (Submission $submission, int $submissionId) use ($acceptedReviewAssignments) {
                $reviewAssignments = $acceptedReviewAssignments->get($submissionId, collect());
                return new SubmissionReviewData($submission, $reviewAssignments, $this->contextId);
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

    private function generateTestData()
    {
        $acceptedReviewAssignments = $this->getAcceptedReviewAssignments();
        $confirmedReviewAssignments = $acceptedReviewAssignments->map(
            fn (Collection $reviewAssignments) => $reviewAssignments->filter(
                fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->isConfirmedByEditor()
            )
        );


        // Option to drop submission with no confirmed reviews. TBD
        //            ->filter(fn (Collection $reviewAssignments) => $reviewAssignments->isNotEmpty());
    }
}
