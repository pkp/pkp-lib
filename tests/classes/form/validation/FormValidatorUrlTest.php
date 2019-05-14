<?php

/**
 * @file tests/classes/form/validation/FormValidatorUrlTest.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUrlTest
 * @ingroup tests_classes_form_validation
 * @see FormValidatorUrl
 *
 * @brief Test class for FormValidatorUrl.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.form.Form');

class FormValidatorUrlTest extends PKPTestCase {
	/**
	 * @covers FormValidatorUrl
	 * @covers FormValidator
	 */
	public function testIsValid() {
		$form = new Form('some template');

		// test valid urls
		$form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
		$validator = new FormValidatorUrl($form, 'testUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertTrue($validator->isValid());
		self::assertEquals(array('testUrl' => array('required', 'url')), $form->cssValidation);

		$form->setData('testUrl', 'http://192.168.0.1/');
		$validator = new FormValidatorUrl($form, 'testUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertTrue($validator->isValid());

		// test invalid urls
		$form->setData('testUrl', 'http//missing-colon.org');
		$validator = new FormValidatorUrl($form, 'testUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertFalse($validator->isValid());

		$form->setData('testUrl', 'http:/missing-slash.org');
		$validator = new FormValidatorUrl($form, 'testUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
		self::assertFalse($validator->isValid());
	}
}
