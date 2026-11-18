<?php

namespace PKP\peerReview;

use APP\submission\Submission;
use Illuminate\Support\Collection;
use PKP\submission\reviewAssignment\ReviewAssignment;

class SubmissionReviewData
{
    public function __construct(
        public readonly Submission $submission,
        /** @var Collection<int, ReviewAssignment> */
        public readonly Collection $reviewAssignments,
        public readonly int $contextId
    ) {
    }
}
