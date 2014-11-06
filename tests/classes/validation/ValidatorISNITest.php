<?php

/**
 * @file tests/classes/validation/ValidatorISNITest.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorISNITest
 * @ingroup tests_classes_validation
 * @see ValidatorISNI
 *
 * @brief Test class for ValidatorISNI.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.validation.ValidatorISNI');

class ValidatorISNITest extends PKPTestCase {
	/**
	 * @covers ValidatorISNI
	 * @covers ValidatorRegExp
	 * @covers Validator
	 */
	public function testValidatorISNI() {
		$validator = new ValidatorISNI();
		self::assertTrue($validator->isValid('0000000218250097')); // Valid
		self::assertTrue($validator->isValid('000000021694233X')); // Valid, with an X as checksum
		self::assertFalse($validator->isValid('0000-0002-1694-233X')); // Has dashes
		self::assertFalse($validator->isValid('21694233X')); // Stripped leading 0s
		self::assertFalse($validator->isValid('http://orcid.org/000000021694233X')); // Is really an ORCID
		self::assertFalse($validator->isValid('000000021694233XY')); // extra character at end
	}
}

?>
