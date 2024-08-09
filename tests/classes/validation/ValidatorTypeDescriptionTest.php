<?php

/**
 * @file tests/classes/validation/ValidatorTypeDescriptionTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorTypeDescriptionTest
 *
 * @ingroup tests_classes_filter
 *
 * @see ValidatorTypeDescription
 *
 * @brief Test class for ValidatorTypeDescription and TypeDescription.
 */

namespace PKP\tests\classes\validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorTypeDescription;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidatorTypeDescription::class)]
class ValidatorTypeDescriptionTest extends PKPTestCase
{
    public function testInstantiateAndCheck()
    {
        $typeDescription = new ValidatorTypeDescription('email');
        self::assertTrue($typeDescription->isCompatible('jerico.dev@gmail.com'));
        self::assertFalse($typeDescription->isCompatible('another string'));
    }

    public function testInstantiateAndCheckWithParameters()
    {
        $typeDescription = new ValidatorTypeDescription('regExp("/123/")');
        self::assertFalse($typeDescription->checkType('some string'));
        self::assertFalse($typeDescription->checkType(new \stdClass()));
        self::assertTrue($typeDescription->checkType('123'));
        self::assertFalse($typeDescription->checkType('abc'));
    }

    public static function typeDescriptorDataProvider(): array
    {
        return [
            'Invalid name' => ['email(xyz]'],
            'Invalid casing' => ['Email'],
            'Invalid character' => ['email&'],
        ];
    }

    #[DataProvider('typeDescriptorDataProvider')]
    public function testInstantiateWithInvalidTypeDescriptor(string $type)
    {
        $this->expectException(\Exception::class); // Trying to instantiate a "validator" type description with an invalid type name "$type"
        $typeDescription = new ValidatorTypeDescription($type);
    }
}
