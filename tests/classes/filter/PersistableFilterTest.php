<?php

/**
 * @file tests/classes/filter/PersistableFilterTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterTest
 * @ingroup tests_classes_filter
 *
 * @see Filter
 *
 * @brief Test class for PersistableFilter.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\EmailFilterSetting;
use PKP\filter\PersistableFilter;
use PKP\filter\TypeDescriptionFactory;
use PKP\tests\PKPTestCase;

class PersistableFilterTest extends PKPTestCase
{
    /**
     * @covers PersistableFilter
     */
    public function testInstantiationAndExecute()
    {
        $constructorArg = PersistableFilter::tempGroup(
            'class::lib.pkp.tests.classes.filter.TestClass1',
            'class::lib.pkp.tests.classes.filter.TestClass2'
        );
        $testFilter = new PersistableFilter($constructorArg);

        // Test getters/setters that are not implicitly tested by other tests
        self::assertInstanceOf('FilterGroup', $testFilter->getFilterGroup());
        $testFilter->setDisplayName('Some other display name');
        $testFilter->setIsTemplate(1);
        self::assertTrue($testFilter->getIsTemplate());
        self::assertEquals(0, $testFilter->getParentFilterId());
        $testFilter->setParentFilterId(1);
        self::assertEquals(1, $testFilter->getParentFilterId());

        // Test settings
        self::assertFalse($testFilter->hasSettings());
        $testSetting = new EmailFilterSetting('testEmail', 'Test Email', 'Test Email is required');
        $testSetting2 = new EmailFilterSetting('testEmail2', 'Test Email2', 'Test Email2 is required');
        $testSetting2->setIsLocalized(true);
        $testFilter->addSetting($testSetting);
        $testFilter->addSetting($testSetting2);
        self::assertEquals(['testEmail' => $testSetting, 'testEmail2' => $testSetting2], $testFilter->getSettings());
        self::assertTrue($testFilter->hasSettings());
        self::assertEquals(['testEmail'], $testFilter->getSettingNames());
        self::assertEquals(['testEmail2'], $testFilter->getLocalizedSettingNames());
        self::assertTrue($testFilter->hasSetting('testEmail'));
        self::assertEquals($testSetting, $testFilter->getSetting('testEmail'));

        // Test type validation.
        $typeDescriptionFactory = TypeDescriptionFactory::getInstance();
        $inputTypeDescription = 'class::lib.pkp.tests.classes.filter.TestClass1';
        $outputTypeDescription = 'class::lib.pkp.tests.classes.filter.TestClass2';
        self::assertEquals($inputTypeDescription, $testFilter->getInputType()->getTypeDescription());
        self::assertEquals($outputTypeDescription, $testFilter->getOutputType()->getTypeDescription());
    }
}
