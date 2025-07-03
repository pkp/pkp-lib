<?php

/**
 * @file tests/classes/filter/TypeDescriptionFactoryTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TypeDescriptionFactoryTest
 *
 * @ingroup tests_classes_filter
 *
 * @see TypeDescriptionFactory
 *
 * @brief Test class for TypeDescriptionFactory.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\TypeDescriptionFactory;
use PKP\tests\classes\filter\TestClass2;
use PKP\tests\classes\filter\TestClass1;
use PKP\filter\ClassTypeDescription;
use PKP\filter\PrimitiveTypeDescription;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TypeDescriptionFactory::class)]
class TypeDescriptionFactoryTest extends PKPTestCase
{
    public function testInstantiateTypeDescription()
    {
        $typeDescriptionFactory = TypeDescriptionFactory::getInstance();

        // Instantiate a primitive type
        $typeDescription = $typeDescriptionFactory->instantiateTypeDescription('primitive::string');
        self::assertInstanceOf(PrimitiveTypeDescription::class, $typeDescription);
        self::assertTrue($typeDescription->isCompatible($object = 'some string'));
        self::assertFalse($typeDescription->isCompatible($object = 5));

        // Instantiate a class type
        $typeDescription = $typeDescriptionFactory->instantiateTypeDescription('class::' . TestClass1::class);
        self::assertInstanceOf(ClassTypeDescription::class, $typeDescription);
        $compatibleObject = new TestClass1();
        $wrongObject = new TestClass2();
        self::assertTrue($typeDescription->isCompatible($compatibleObject));
        self::assertFalse($typeDescription->isCompatible($wrongObject));

        // Test invalid descriptions
        self::assertNull($typeDescriptionFactory->instantiateTypeDescription('string'));
        self::assertNull($typeDescriptionFactory->instantiateTypeDescription('unknown-namespace::xyz'));
    }
}
