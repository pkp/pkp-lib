<?php

/**
 * @file tests/classes/validation/ValidatorEmailTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmailTest
 *
 * @ingroup tests_classes_validation
 *
 * @see ValidatorEmail
 *
 * @brief Test class for ValidatorEmail.
 */

namespace PKP\tests\classes\validation;

use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorEmail;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidatorEmail::class)]
class ValidatorEmailTest extends PKPTestCase
{
    public function testValidatorEmail()
    {
        $validator = new ValidatorEmail();
        self::assertTrue($validator->isValid('some.address@gmail.com'));
        self::assertTrue($validator->isValid('anything@localhost'));
        self::assertTrue($validator->isValid("allowedchars!#$%&'*+./=?^_`{|}@gmail.com"));
        self::assertTrue($validator->isValid('"quoted.username"@gmail.com'));
        self::assertFalse($validator->isValid('anything else'));
        self::assertFalse($validator->isValid('double@@gmail.com'));
        self::assertFalse($validator->isValid('no"quotes"in.middle@gmail.com'));
    }
}
