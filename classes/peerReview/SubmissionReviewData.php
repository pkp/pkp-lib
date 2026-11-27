<?php

/**
 * @file classes/peerReview/SubmissionReviewData.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionReviewData
 *
 * @brief Data Transfer Object (DTO) for providing peer-review related API resources with initial and pre-cached data.
 */

namespace PKP\peerReview;

use APP\submission\Submission;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

readonly class SubmissionReviewData
{
    public function __construct(
        public Submission $submission,
        public Context $context,
        /** @var Collection<int, ReviewAssignment> */
        public Collection $reviewAssignments,
        /** @var Collection<int, ReviewerRecommendation> keyed by reviewerRecommendationId */
        public Collection $recommendationTypes,
        public array $pmurReviewRoundIds = [],
    ) {
    }
}
