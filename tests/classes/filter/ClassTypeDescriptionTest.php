<?php

/**
 * @file tests/classes/filter/ClassTypeDescriptionTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ClassTypeDescriptionTest
 *
 * @ingroup tests_classes_filter
 *
 * @see ClassTypeDescription
 *
 * @brief Test class for ClassTypeDescription.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\ClassTypeDescription;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassTypeDescription::class)]
class ClassTypeDescriptionTest extends PKPTestCase
{
    public function testInstantiateAndCheck()
    {
        $typeDescription = new ClassTypeDescription(TestClass1::class);
        $compatibleObject = new TestClass1();
        $wrongObject = new TestClass2();
        self::assertTrue($typeDescription->isCompatible($compatibleObject));
        self::assertFalse($typeDescription->isCompatible($wrongObject));
    }
}
