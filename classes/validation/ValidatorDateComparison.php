<?php

/**
 * @file classes/validation/ValidatorDateComparison.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorDateComparison
 *
 * @ingroup validation
 *
 * @see Validator
 *
 * @brief Validation check for comparing with a given date
 */

namespace PKP\validation;

use Carbon\Carbon;
use DateTimeInterface;
use PKP\validation\enums\DateComparisonRule;
use PKP\validation\ValidatorFactory;

class ValidatorDateComparison extends Validator
{
    /**
     * Constructor.
     *
     * @param DateTimeInterface|Carbon  $comparingDate  the comparing date
     * @param DateComparisonRule        $rule           the comparing rule
     */
    public function __construct(
        protected DateTimeInterface|Carbon $comparingDate,
        protected DateComparisonRule $rule
    )
    {
        $this->comparingDate = $comparingDate instanceof Carbon
            ? $comparingDate
            : Carbon::parse($comparingDate);
    }

    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => [
                'date', 
                $this->rule->value . ':' . $this->comparingDate->toDateString()
            ]]
        );

        return $validator->passes();
    }
}
