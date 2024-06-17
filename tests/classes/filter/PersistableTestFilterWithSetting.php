<?php

/**
 * @file tests/classes/filter/PersistableTestFilterWithSetting.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PersistableTestFilterWithSetting
 *
 * @brief Test class to be used to test the FilterDAO.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\FilterGroup;
use PKP\filter\FilterSetting;
use PKP\filter\PersistableFilter;

class PersistableTestFilterWithSetting extends PersistableFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->addSetting(new FilterSetting('some-key', null, null));
        parent::__construct($filterGroup);
    }

    /**
     * Just for testing purposes; do anything
     */
    public function &process(&$input)
    {
        return $input;
    }
}
