<?php
/**
 * @file classes/decision/types/RecommendResubmit.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A recommendation to request revisions be requested and sent for another round of review.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\decision\Type;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\IsRecommendation;

class RecommendResubmit extends Type
{
    use InExternalReviewRound;
    use IsRecommendation;

    public function getDecision(): int
    {
        return Decision::RECOMMEND_RESUBMIT;
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
        return __('editor.submission.recommend.resubmit', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.recommend.resubmit.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.recommend.resubmit.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.recommend.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.recommend.completed.description');
    }
}
