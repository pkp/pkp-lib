<?php

/**
 * @file controllers/grid/users/reviewer/form/traits/HasReviewDueDate.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasReviewDueDate
 *
 * @brief Helper trait to get the review submit and/or response due dates
 */

namespace PKP\controllers\grid\users\reviewer\form\traits;

use Carbon\Carbon;
use PKP\context\Context;

trait HasReviewDueDate
{
    public const REVIEW_SUBMIT_DEFAULT_DUE_WEEKS = 4;
    public const REVIEW_RESPONSE_DEFAULT_DUE_WEEKS = 3;

    /**
     * Get the review submit due dates
     */
    public function getReviewSubmitDueDate(Context $context): Carbon
    {
        $numWeeks = (int) $context->getData('numWeeksPerReview');
        
        if ($numWeeks <= 0) {
            $numWeeks = static::REVIEW_SUBMIT_DEFAULT_DUE_WEEKS;
        }

        return Carbon::today()->endOfDay()->addWeeks($numWeeks);
    }

    /**
     * Get the review response due dates
     */
    public function getReviewResponseDueDate(Context $context): Carbon
    {
        $numWeeks = (int) $context->getData('numWeeksPerResponse');
        
        if ($numWeeks <= 0) {
            $numWeeks = static::REVIEW_RESPONSE_DEFAULT_DUE_WEEKS;
        }

        return Carbon::today()->endOfDay()->addWeeks($numWeeks);
    }
    
    /**
     * Get the review submit and response due dates
     */
    public function getDueDates(Context $context): array
    {   
        return [
            $this->getReviewSubmitDueDate($context)->getTimestamp(),
            $this->getReviewResponseDueDate($context)->getTimestamp(),
        ];
    }
}
