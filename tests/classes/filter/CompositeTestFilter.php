<?php

/**
 * @file tests/classes/filter/CompositeTestFilter.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CompositeTestFilter
 *
 * @brief Test class to be used to test composite filters.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\CompositeFilter;

class CompositeTestFilter extends CompositeFilter
{
    /**
     * Just for testing purposes; do anything
     */
    public function &process(&$input)
    {
        return $input;
    }
}
