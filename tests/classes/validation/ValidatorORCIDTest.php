<?php

/**
 * @file tests/classes/validation/ValidatorORCIDTest.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorORCIDTest
 *
 * @ingroup tests_classes_validation
 *
 * @see ValidatorORCID
 *
 * @brief Test class for ValidatorORCID.
 */

namespace PKP\tests\classes\validation;

use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorORCID;

class ValidatorORCIDTest extends PKPTestCase
{
    /**
     * @covers ValidatorORCID
     * @covers ValidatorRegExp
     * @covers Validator
     */
    public function testValidatorORCID()
    {
        $validator = new ValidatorORCID();
        self::assertFalse($validator->isValid('http://orcid.org/0000-0002-1825-0097')); // Invalid (http)
        self::assertTrue($validator->isValid('https://orcid.org/0000-0002-1825-0097')); // Valid (https)
        self::assertFalse($validator->isValid('ftp://orcid.org/0000-0002-1825-0097')); // Invalid (FTP scheme)
        self::assertTrue($validator->isValid('https://orcid.org/0000-0002-1694-233X')); // Valid, with an X in the last digit
        self::assertFalse($validator->isValid('0000-0002-1694-233X')); // Missing URI component
        self::assertFalse($validator->isValid('000000021694233X')); // Missing dashes, URI component
    }
}
