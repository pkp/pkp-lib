<?php

/**
 * @file tests/classes/validation/ValidatorEmailTest.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmailTest
 * @ingroup tests_classes_validation
 * @see ValidatorEmail
 *
 * @brief Test class for ValidatorEmail.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.validation.ValidatorEmail');

class ValidatorEmailTest extends PKPTestCase {
	/**
	 * @covers ValidatorEmail
	 * @covers ValidatorRegExp
	 */
	public function testValidatorEmail() {
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

