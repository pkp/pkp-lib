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
    public const REVIEW_SUBMIT_DEFAULT_DUE_DAYS = 30;
    public const REVIEW_RESPONSE_DEFAULT_DUE_DAYS = 30;

    /**
     * Get the review submit due dates
     */
    public function getReviewSubmitDueDate(Context $context): Carbon
    {
        $numDays = (int) $context->getData('numDaysPerReview');
        
        if ($numDays <= 0) {
            $numDays = static::REVIEW_SUBMIT_DEFAULT_DUE_DAYS;
        }

        return Carbon::today()->endOfDay()->addDays($numDays);
    }

    /**
     * Get the review response due dates
     */
    public function getReviewResponseDueDate(Context $context): Carbon
    {
        $numDays = (int) $context->getData('numDaysPerResponse');
        
        if ($numDays <= 0) {
            $numDays = static::REVIEW_RESPONSE_DEFAULT_DUE_DAYS;
        }

        return Carbon::today()->endOfDay()->addDays($numDays);
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
