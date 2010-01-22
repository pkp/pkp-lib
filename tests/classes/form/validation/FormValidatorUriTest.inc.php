<?php

/**
 * @file tests/metadata/FormValidatorUriTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUriTest
 * @ingroup tests_classes_validation
 * @see FormValidatorUri
 *
 * @brief Test class for FormValidatorUri.
 */

import('tests.PKPTestCase');
import('form.Form');
import('form.validation.FormValidator');

class FormValidatorUriTest extends PKPTestCase {
	/**
	 * @covers FormValidatorUri::FormValidatorUri()
	 * @covers FormValidatorUri::isValid()
	 */
	public function testIsValid() {
		$form = new Form('some template');

		// test valid urls
		$form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message', array('http'));
		self::assertTrue($validator->isValid());

		$form->setData('testUrl', 'https://some.domain.org:8080');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message', array('https'));
		self::assertTrue($validator->isValid());

		$form->setData('testUrl', 'ftp://192.168.0.1/');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message');
		self::assertTrue($validator->isValid());

		// test invalid urls
		$form->setData('testUrl', 'gopher://some.domain.org/');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message', array('http'));
		self::assertFalse($validator->isValid());

		$form->setData('testUrl', 'http://some.domain.org/#frag1#frag2');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message', array('http'));
		self::assertFalse($validator->isValid());

		$form->setData('testUrl', 'http://256.168.0.1/');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message', array('http'));
		self::assertFalse($validator->isValid());
	}

	/**
	 * @covers FormValidatorUri::getRegExp()
	 */
	public function testGetRegExp() {
		$form = new Form('some template');

		// test valid urls
		$form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
		$validator = new FormValidatorUri($form, 'testUrl', 'required', 'some message');
		self::assertEquals('&^'.PCRE_URI.'$&i', $validator->getRegexp());
	}
}
?>