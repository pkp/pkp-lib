<?php

/**
 * @file tests/metadata/FormValidatorPostTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorPostTest
 * @ingroup tests_classes_form_validation
 * @see FormValidatorPost
 *
 * @brief Test class for FormValidatorPost.
 */

import('tests.PKPTestCase');
import('form.Form');
import('core.Request'); // This will import the mock request

class FormValidatorPostTest extends PKPTestCase {
	/**
	 * @covers FormValidatorPost
	 * @covers FormValidator
	 */
	public function testIsValid() {
		// Instantiate test validator
		$form = new Form('some template');
		$validator = new FormValidatorPost($form, 'some.message.key');

		Request::setRequestMethod('POST');
		self::assertTrue($validator->isValid());

		Request::setRequestMethod('GET');
		self::assertFalse($validator->isValid());
	}
}
?>