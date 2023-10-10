<?php

/**
 * @file tests/classes/validation/ValidatorISSNTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorISSNTest
 *
 * @ingroup tests_classes_validation
 *
 * @see ValidatorISSN
 *
 * @brief Test class for ValidatorISSN.
 */

namespace PKP\tests\classes\validation;

use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorISSN;

class ValidatorISSNTest extends PKPTestCase
{
    /**
     * @covers ValidatorISSN
     * @covers ValidatorRegExp
     * @covers Validator
     */
    public function testValidatorISSN()
    {
        $validator = new ValidatorISSN();
        self::assertTrue($validator->isValid('0378-5955')); // Valid
        self::assertFalse($validator->isValid('0378-5955f')); // Overlong
        self::assertFalse($validator->isValid('03785955')); // Missing dash
        self::assertFalse($validator->isValid('1234-5678')); // Wrong check digit
        self::assertTrue($validator->isValid('0031-790X')); // Check digit is X
        self::assertTrue($validator->isValid('1945-2020')); // Check digit is 0
    }
}
