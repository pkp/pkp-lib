<?php
/**
 * @file classes/decision/types/RecommendDecline.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A recommendation that the submission be declined.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\decision\DecisionType;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\IsRecommendation;

class RecommendDecline extends DecisionType
{
    use InExternalReviewRound;
    use IsRecommendation;

    public function getDecision(): int
    {
        return Decision::RECOMMEND_DECLINE;
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
        return __('editor.submission.recommend.decline', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.recommend.decline.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.recommend.decline.log';
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
        return __('editor.submission.decision.decline');
    }
}
