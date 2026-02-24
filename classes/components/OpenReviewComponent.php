<?php

/**
 * @file components/OpenReviewComponent.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenReviewComponent
 *
 * @ingroup classes_components
 *
 * @brief A class to prepare configurations for PkpOpenReview UI component.
 */

namespace PKP\components;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\LazyCollection;
use PKP\API\v1\peerReviews\resources\SubmissionPeerReviewSummaryResource;
use PKP\submission\reviewer\recommendation\enums\ReviewerRecommendationType;

class OpenReviewComponent
{
    private LazyCollection $publicationsPeerReviews;
    private array $submissionPeerReviewSummary;

    public function __construct(Submission $submission)
    {
        $this->publicationsPeerReviews = Repo::publication()->getPublicPeerReviews(
            $submission->getPublishedPublications()
        );

        $this->submissionPeerReviewSummary = (new SubmissionPeerReviewSummaryResource($submission))
            ->resolve();
    }

    /**
     * Get the locale keys to expose for the PkpOpenReview component.
     */
    public function getLocaleKeys(): array
    {
        return [
            'openReview.sortBy',
            'openReview.sortByReviewerName',
            'openReview.reviewCount',
            'openReview.fullReview',
            'openReview.noCommentsAvailable',
            'openReview.readReview',
            'openReview.readResponse',
            'openReview.hideResponse',
            'publication.versionStage.versionOfRecord',
            'common.pagination.previous',
            'common.pagination.next',
            'submission.reviewRound.authorResponse',
            // PkpOpenReviewSummary component locale keys
            'openReview.title',
            'openReview.status',
            'openReview.statusInProgress',
            'openReview.recommendationItem',
            'openReview.reviewersContributed',
            'openReview.howDecisionsSummarized',
            'openReview.howDecisionsSummarizedDescription',
            'openReview.versionsPublished',
            'openReview.currentVersion',
            'openReview.reviewModel',
            'openReview.seeFullRecord',
            'openReview.recommendationItemSeparator',
            'openReview.authorAffiliationSeparator',
            'common.inParenthesis'
        ];
    }

    /**
     * Get the configuration for the PkpOpenReview component.
     */
    public function getConfig(): array
    {
        return [
            'publicationsPeerReviews' => $this->publicationsPeerReviews->all(),
            'submissionPeerReviewSummary' => $this->submissionPeerReviewSummary,
        ];
    }

    /**
     * Get the constants for reviewer recommendation types.
     */
    public function getConstants(): array
    {
        return [
            'reviewerRecommendationType' => [
                'APPROVED' => ReviewerRecommendationType::APPROVED->value,
                'NOT_APPROVED' => ReviewerRecommendationType::NOT_APPROVED->value,
                'REVISIONS_REQUESTED' => ReviewerRecommendationType::REVISIONS_REQUESTED->value,
                'WITH_COMMENTS' => ReviewerRecommendationType::WITH_COMMENTS->value,
            ],
        ];
    }

    /**
     * Get SVG icons used by the PkpOpenReview component.
     */
    public function getSvgIcons(): array
    {
        return [
            'ReviewApproved',
            'ReviewNotApproved',
            'ReviewRevisionsRequested',
            'ReviewComments',
            'ReviewAuthorResponse',
        ];
    }
}
