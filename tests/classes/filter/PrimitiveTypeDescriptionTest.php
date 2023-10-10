<?php

/**
 * @file tests/classes/filter/PrimitiveTypeDescriptionTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrimitiveTypeDescriptionTest
 *
 * @ingroup tests_classes_filter
 *
 * @see PrimitiveTypeDescription
 *
 * @brief Test class for PrimitiveTypeDescription and TypeDescription.
 *
 * NB: We cannot test TypeDescription without subclasses as it is abstract
 * and cannot be mocked because it relies on an abstract method in its
 * constructor. There's no way to mock methods called in the constructor
 * as the constructor is called before we get a chance to define mock method
 * return values.
 */

namespace PKP\tests\classes\filter;

use PKP\filter\PrimitiveTypeDescription;
use PKP\tests\PKPTestCase;
use stdClass;

class PrimitiveTypeDescriptionTest extends PKPTestCase
{
    /**
     * @covers PrimitiveTypeDescription
     * @covers TypeDescription
     */
    public function testInstantiateAndCheck()
    {
        $typeDescription = new PrimitiveTypeDescription('string');
        self::assertTrue($typeDescription->isCompatible('some string'));
        self::assertFalse($typeDescription->isCompatible(5));
        self::assertFalse($typeDescription->isCompatible([5]));

        self::assertEquals('string', $typeDescription->getTypeName());
        self::assertEquals('primitive::string', $typeDescription->getTypeDescription());

        $typeDescription = new PrimitiveTypeDescription('integer');
        self::assertTrue($typeDescription->isCompatible(2));
        self::assertFalse($typeDescription->isCompatible('some string'));
        self::assertFalse($typeDescription->isCompatible(5.5));
        self::assertFalse($typeDescription->isCompatible(new stdClass()));

        $typeDescription = new PrimitiveTypeDescription('float');
        self::assertTrue($typeDescription->isCompatible(2.5));
        self::assertFalse($typeDescription->isCompatible('some string'));
        self::assertFalse($typeDescription->isCompatible(5));

        $typeDescription = new PrimitiveTypeDescription('boolean');
        self::assertTrue($typeDescription->isCompatible(true));
        self::assertTrue($typeDescription->isCompatible(false));
        self::assertFalse($typeDescription->isCompatible(1));
        self::assertFalse($typeDescription->isCompatible(''));

        $typeDescription = new PrimitiveTypeDescription('integer[]');
        self::assertTrue($typeDescription->isCompatible([2]));
        self::assertTrue($typeDescription->isCompatible([2, 5]));
        self::assertFalse($typeDescription->isCompatible(2));

        $typeDescription = new PrimitiveTypeDescription('integer[1]');
        self::assertTrue($typeDescription->isCompatible([2]));
        self::assertFalse($typeDescription->isCompatible([2, 5]));
        self::assertFalse($typeDescription->isCompatible(2));
    }

    /**
     * Provides test data
     */
    public function typeDescriptorDataProvider(): array
    {
        return [
            'An unknown type name will cause an error' => ['xyz'],
            'We don\'t allow multi-dimensional arrays' => ['integer[][]'],
            'An invalid cardinality will also cause an error' => ['integer[x]'],
        ];
    }

    /**
     * @covers PrimitiveTypeDescription
     * @covers TypeDescription
     *
     * @dataProvider typeDescriptorDataProvider
     */
    public function testInstantiateWithInvalidTypeDescriptor(string $type)
    {
        $this->expectException(\Exception::class); // Trying to instantiate a "primitive" type description with an invalid type name "$type"
        $typeDescription = new PrimitiveTypeDescription($type);
    }
}
