<?php

/**
 * @file tests/metadata/FormValidatorUrlTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUrlTest
 * @ingroup tests_classes_validation
 * @see FormValidatorUrl
 *
 * @brief Test class for FormValidatorUrl.
 */

import('tests.PKPTestCase');
import('form.Form');
import('form.validation.FormValidator');

class FormValidatorUrlTest extends PKPTestCase {
	/**
	 * @covers FormValidatorUrl::FormValidatorUrl()
	 * @covers FormValidatorUrl::isValid()
	 */
	public function testIsValid() {
		$form = new Form('some template');

		// test valid urls
		$form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertTrue($validator->isValid());

		$form->setData('testUrl', 'http://192.168.0.1/');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertTrue($validator->isValid());

		// test invalid urls
		$form->setData('testUrl', 'gopher://some.domain.org/');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertFalse($validator->isValid());

		$form->setData('testUrl', 'http://some.domain.org/#frag1#frag2');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertFalse($validator->isValid());

		$form->setData('testUrl', 'http://256.168.0.1/');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertFalse($validator->isValid());
	}

	/**
	 * @covers FormValidatorUrl::getRegExp()
	 */
	public function testGetRegExp() {
		$form = new Form('some template');

		// test valid urls
		$form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
		$validator = new FormValidatorUrl($form, 'testUrl', 'required', 'some message');
		self::assertEquals('&^(?:(http|https|ftp):)?(?://(?:((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();:\&=+$,])*)@)?(?:((?:[a-z0-9](?:[-a-z0-9]*[a-z0-9])?\.)*[a-z](?:[a-z0-9]+)?\.?)|([0-9]{1,3}(?:\.[0-9]{1,3}){3}))(?::([0-9]*))?)((?:/(?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'():@\&=+$,;])*)*/?)?(?:\?([^#]*))?(?:\#((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();/?:@\&=+$,])*))?$&i', $validator->getRegexp());
	}
}
?>