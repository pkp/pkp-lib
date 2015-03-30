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
		$validator = new ValidatorInSet(array(1, 2, 'a', 'B'));
		self::assertTrue($validator->isValid(0)); // Warning: depends on loose in_array(), so a value of 0 will match any string
		self::assertTrue($validator->isValid(1)); // Value in set
		self::assertFalse($validator->isValid('b')); // Value not in set
		self::assertTrue($validator->isValid('1')); // Loose in_array() checking matches strings to numerics
		$validator = new ValidatorInSet(array());
		self::assertFalse($validator->isValid(1)); // Any value in empty set
		$validator = new ValidatorInSet(array(0, 'anything')); // Warning: depends on loose in_array(), so a value of 0 will match any string
		self::assertTrue($validator->isValid('something')); // Any string value
	}
}

?>
