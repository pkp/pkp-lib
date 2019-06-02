<?php

/**
 * @file tests/classes/validation/ValidatorUrlTest.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorUrlTest
 * @ingroup tests_classes_validation
 * @see ValidatorUrl
 *
 * @brief Test class for ValidatorUrl.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.validation.ValidatorUrl');

class ValidatorUrlTest extends PKPTestCase {
	/**
	 * @covers ValidatorUrl
	 * @covers ValidatorRegExp
	 * @covers Validator
	 */
	public function testValidatorUrlAndUri() {
		$validator = new ValidatorUrl();
		self::assertTrue($validator->isValid('ftp://some.download.com/'));
		self::assertTrue($validator->isValid('http://some.site.org/'));
		self::assertTrue($validator->isValid('https://some.site.org/'));
		self::assertTrue($validator->isValid('gopher://another.site.org/'));
		self::assertFalse($validator->isValid('anything else'));
		self::assertTrue($validator->isValid('http://189.63.74.2/'));
		self::assertTrue($validator->isValid('http://257.63.74.2/'));
		self::assertFalse($validator->isValid('http://189.63.74.2.7/'));
	}
}

