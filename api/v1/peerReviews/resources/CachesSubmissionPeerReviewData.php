<?php

/**
 * @file api/v1/peerReviews/resources/CachesSubmissionPeerReviewData.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class CachesSubmissionPeerReviewData
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Trait for accessing cached submission peer review data between full and summary data.
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\submission\Submission;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewRound\ReviewRound;

trait CachesSubmissionPeerReviewData
{
    /** @var SubmissionPeerReviewDataCache|null $peerReviewDataCache Data cache object */
    private ?SubmissionPeerReviewDataCache $peerReviewDataCache = null;

    /** @var Submission $resource Submission object used within JsonResource classes */
    public $resource;

    /**
     * Sets a fully hydrated data cache to class using this trait
     */
    public function useDataCache(SubmissionPeerReviewDataCache $peerReviewDataCache): self
    {
        $this->peerReviewDataCache = $peerReviewDataCache;
        return $this;
    }

    /**
     * Used by other methods to fetch or initialize data cache to retrieved shared data
     */
    private function getDataCache(): SubmissionPeerReviewDataCache
    {
        if ($this->peerReviewDataCache === null) {
            $this->peerReviewDataCache = new SubmissionPeerReviewDataCache($this->resource);
        }

        return $this->peerReviewDataCache;
    }

    private function getContext(): Context
    {
        return $this->getDataCache()->context;
    }

    /**
     * @return Collection<int, ReviewRound>
     */
    private function getPublicReviewRounds(): Collection
    {
        return $this->getDataCache()->publicReviewRounds;
    }

    /**
     * @return Collection<int, ReviewAssignment>
     */
    private function getPublicAcceptedReviewAssignments(): Collection
    {
        return $this->getDataCache()->publicAcceptedReviewAssignments;
    }

    /**
     * @return Collection<int, ReviewerRecommendation>
     */
    private function getAvailableRecommendationTypes(): Collection
    {
        return $this->getDataCache()->availableRecommendationTypes;
    }
}
