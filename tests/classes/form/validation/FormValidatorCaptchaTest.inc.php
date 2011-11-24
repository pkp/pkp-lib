<?php

/**
 * @file tests/metadata/FormValidatorCaptchaTest.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorCaptchaTest
 * @ingroup tests_classes_form_validation
 * @see FormValidatorCaptcha
 *
 * @brief Test class for FormValidatorCaptcha.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.form.Form');

class FormValidatorCaptchaTest extends PKPTestCase {
	/**
	 * @covers FormValidatorCaptcha
	 * @covers FormValidator
	 */
	public function testIsValid() {
		// Test form
		$form = new Form('some template');
		$form->setData('testCaptchaId', 'test captcha id');

		// Create a test Captcha
		import('lib.pkp.classes.captcha.Captcha');
		$captcha = new Captcha();
		$captcha->setValue('expected captcha value');
		$this->registerMockCaptchaDAO($captcha);

		// Instantiate validator
		$validator = new FormValidatorCaptcha($form, 'testData', 'testCaptchaId', 'some.message.key');

		// Test valid captcha
		$form->setData('testData', 'expected captcha value');
		self::assertTrue($validator->isValid());

		// Simulate invalid captcha value
		$form->setData('testData', 'unexpected captcha value');
		self::assertFalse($validator->isValid());

		// Simulate invalid captcha id
		$this->registerMockCaptchaDAO(null);
		$form->setData('testData', 'expected captcha value');
		self::assertFalse($validator->isValid());
	}

	private function registerMockCaptchaDAO($returnValueForGetCaptcha) {
		// Mock the CaptchaDAO
		$mockCaptchaDAO = $this->getMock('CaptchaDAO', array('getCaptcha', 'deleteObject'));

		// Set up the mock getCaptcha() method
		$mockCaptchaDAO->expects($this->any())
		               ->method('getCaptcha')
		               ->with('test captcha id')
		               ->will($this->returnValue($returnValueForGetCaptcha));

		// Set up the mock deleteObject() method
		if (is_null($returnValueForGetCaptcha)) {
			$mockCaptchaDAO->expects($this->never())
			               ->method('deleteObject');
		} else {
			$mockCaptchaDAO->expects($this->any())
			               ->method('deleteObject')
			               ->with($returnValueForGetCaptcha)
			               ->will($this->returnValue(true));
		}

		DAORegistry::registerDAO('CaptchaDAO', $mockCaptchaDAO);
	}
}
?>
