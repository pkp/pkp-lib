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
		self::assertTrue($validator->isValid('some.address@localhost'));
		self::assertTrue($validator->isValid('some.address@127.0.0.1'));
		self::assertFalse($validator->isValid('anything else'));

		self::assertEquals('/^[-a-z0-9!#\$%&\'\*\+\/=\?\^_\`\{\|\}~]+(\.[-a-z0-9!#\$%&\'\*\+\/=\?\^_\`\{\|\}~]+)*@(localhost|(127(?:\.[0-9]+){0,2}\.[0-9]+|^(?:0*\:)*?:?0*1)|((([a-z0-9]([-a-z0-9]*[a-z0-9]+)?){1,63}\.)+([a-z0-9]([-a-z0-9]*[a-z0-9]+)?){2,63}))$/i', ValidatorEmail::getRegexp());
	}
}
?>
