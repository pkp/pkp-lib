<?php
/**
 * @file classes/decision/types/RecommendRevisions.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A recommendation to request revisions before accepting a submission.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\decision\DecisionType;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\IsRecommendation;

class RecommendRevisions extends DecisionType
{
    use InExternalReviewRound;
    use IsRecommendation;

    public function getDecision(): int
    {
        return Decision::RECOMMEND_PENDING_REVISIONS;
    }

    public function getNewStageId(): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.recommend.revisions', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.recommend.revisions.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.recommend.revisions.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.recommend.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.recommend.completed.description');
    }

    public function getRecommendationLabel(): string
    {
        return __('editor.submission.decision.requestRevisions');
    }
}
