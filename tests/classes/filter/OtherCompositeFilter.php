<?php

/**
 * @file tests/classes/filter/OtherCompositeFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OtherCompositeFilter
 *
 * @ingroup tests_classes_filter
 *
 * @brief Test class to be used/instantiated by ClassTypeDescriptionTest.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\CompositeFilter;

class OtherCompositeFilter extends CompositeFilter
{
    /**
     * Just for testing purposes; do anything
     */
    public function &process(&$input)
    {
        return $input;
    }
}
