<?php

/**
 * @file classes/validation/enums/DateComparisonRule.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DateComparisonRule
 *
 * @ingroup validation
 *
 * @brief Enumeration for date comparison rules
 */

namespace PKP\validation\enums;

enum DateComparisonRule: string
{
    case EQUAL = 'date_equals';
    case GREATER = 'after';
    case LESSER = 'before';
    case GREATER_OR_EQUAL = 'after_or_equal';
    case LESSER_OR_EQUAL = 'before_or_equal';
}
