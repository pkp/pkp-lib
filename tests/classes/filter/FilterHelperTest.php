<?php

/**
 * @file tests/classes/filter/FilterHelperTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterHelperTest
 *
 * @ingroup tests_classes_filter
 *
 * @see FilterHelper
 *
 * @brief Test class for FilterHelper.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\CompositeFilter;
use PKP\filter\FilterGroup;
use PKP\filter\FilterHelper;
use PKP\filter\FilterSetting;
use PKP\filter\PersistableFilter;
use PKP\tests\PKPTestCase;

class FilterHelperTest extends PKPTestCase
{
    /**
     * @covers FilterHelper
     */
    public function testCompareFilters()
    {
        $filterHelper = new FilterHelper();

        $someGroup = new FilterGroup();
        $someGroup->setInputType('primitive::string');
        $someGroup->setOutputType('primitive::string');

        $filterA = new PersistableFilter($someGroup);
        $filterBSettings = ['some-key' => 'some-value'];
        $filterBSubfilters = [];
        self::assertFalse($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterA->addSetting(new FilterSetting('some-key', null, null));
        self::assertFalse($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterA->setData('some-key', 'some-value');
        self::assertTrue($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterA = new CompositeFilter($someGroup);
        $filterBSettings = [];
        $filterBSubfilter = new CompositeFilter($someGroup);
        $filterBSubfilter->setSequence(1);
        $filterBSubfilters = [$filterBSubfilter];
        self::assertFalse($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterASubfilter = new OtherCompositeFilter($someGroup);
        $filterA->addFilter($filterASubfilter);
        self::assertFalse($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterA = new CompositeFilter($someGroup);
        $filterASubfilter = new CompositeFilter($someGroup);
        $filterA->addFilter($filterASubfilter);
        self::assertTrue($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterBSubfilter->addSetting(new FilterSetting('some-key', null, null));
        $filterASubfilter->addSetting(new FilterSetting('some-key', null, null));
        $filterBSubfilter->setData('some-key', 'some-value');
        self::assertFalse($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));

        $filterASubfilter->setData('some-key', 'some-value');
        self::assertTrue($filterHelper->compareFilters($filterA, $filterBSettings, $filterBSubfilters));
    }
}
