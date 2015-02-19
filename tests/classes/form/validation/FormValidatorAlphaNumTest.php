<?php

/**
 * @file tests/classes/form/validation/FormValidatorAlphaNumTest.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorAlphaNumTest
 * @ingroup tests_classes_form_validation
 * @see FormValidatorAlphaNum
 *
 * @brief Test class for FormValidatorAlphaNum.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.form.Form');

class FormValidatorAlphaNumTest extends PKPTestCase {
	/**
	 * @covers FormValidatorAlphaNum
	 * @covers FormValidator
	 */
	public function testIsValid() {
		$form = new Form('some template');

		// Allowed characters are a-z, A-Z, 0-9, -, _. The characters - and _ are
		// not allowed at the start of the string.
		$form->setData('testData', 'a-Z0123_bKj');
		$validator = new FormValidatorAlphaNum($form, 'testData', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertTrue($validator->isValid());

		// Test invalid strings
		$form->setData('testData', '-Z0123_bKj');
		$validator = new FormValidatorAlphaNum($form, 'testData', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertFalse($validator->isValid());

		$form->setData('testData', 'abc#def');
		$validator = new FormValidatorAlphaNum($form, 'testData', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertFalse($validator->isValid());
	}
}
?>
