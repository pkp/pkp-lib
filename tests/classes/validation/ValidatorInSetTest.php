<?php

/**
 * @file tests/classes/validation/ValidatorInSetTest.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorInSetTest
 * @ingroup tests_classes_validation
 * @see ValidatorInSet
 *
 * @brief Test class for ValidatorInSet.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.validation.ValidatorInSet');

class ValidatorInSetTest extends PKPTestCase {
	/**
	 * @covers ValidatorInSet
	 * @covers Validator
	 */
	public function testValidatorInSet() {
		$validator = new ValidatorInSet(array(0, 1, 'a', 'B'));
		self::assertTrue($validator->isValid(0)); // Valid for logically false variable
		self::assertTrue($validator->isValid(1)); // Valid
		self::assertFalse($validator->isValid('b')); // Loose in_array() checking
		$validator = new ValidatorInSet(array());
		self::assertFalse($validator->isValid(1)); // Any value in empty set
	}
}

?>
