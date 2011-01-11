<?php

/**
 * @defgroup tests_classes_i18n
 */

/**
 * @file tests/classes/i18n/PKPLocaleTest.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPLocaleTest
 * @ingroup tests_classes_i18n
 * @see PKPLocale
 *
 * @brief Tests for the PKPLocale class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.i18n.PKPLocale');

class PKPLocaleTest extends PKPTestCase {
	/**
	 * @covers PKPLocale
	 */
	public function testGetLocaleStylesheet() {
		self::assertNull(Locale::getLocaleStyleSheet('en_US'));
		self::assertEquals('pt.css', Locale::getLocaleStyleSheet('pt_BR'));
		self::assertNull(Locale::getLocaleStyleSheet('xx_XX'));
	}

	/**
	 * @covers PKPLocale
	 */
	public function testIsLocaleComplete() {
		self::assertTrue(Locale::isLocaleComplete('en_US'));
		self::assertFalse(Locale::isLocaleComplete('pt_BR'));
		self::assertFalse(Locale::isLocaleComplete('xx_XX'));
	}

	/**
	 * @covers PKPLocale
	 */
	public function testGetAllLocales() {
		$expectedLocales = array(
			'en_US' => 'English',
			'pt_BR' => 'Portuguese (Brazil)',
			'pt_PT' => 'Portuguese (Portugal)',
			'de_DE' => 'German'
		);
		self::assertEquals($expectedLocales, Locale::getAllLocales());
	}

	/**
	 * @covers PKPLocale
	 */
	public function testGet3LetterFrom2LetterIsoLanguage() {
		self::assertEquals('eng', Locale::get3LetterFrom2LetterIsoLanguage('en'));
		self::assertEquals('por', Locale::get3LetterFrom2LetterIsoLanguage('pt'));
		self::assertNull(Locale::get3LetterFrom2LetterIsoLanguage('xx'));
	}

	/**
	 * @covers PKPLocale
	 */
	public function testGet2LetterFrom3LetterIsoLanguage() {
		self::assertEquals('en', Locale::get2LetterFrom3LetterIsoLanguage('eng'));
		self::assertEquals('pt', Locale::get2LetterFrom3LetterIsoLanguage('por'));
		self::assertNull(Locale::get2LetterFrom3LetterIsoLanguage('xxx'));
	}

	/**
	 * @covers PKPLocale
	 */
	public function testGet3LetterIsoFromLocale() {
		self::assertEquals('eng', Locale::get3LetterIsoFromLocale('en_US'));
		self::assertEquals('por', Locale::get3LetterIsoFromLocale('pt_BR'));
		self::assertEquals('por', Locale::get3LetterIsoFromLocale('pt_PT'));
		self::assertNull(Locale::get3LetterIsoFromLocale('xx_XX'));
	}

	/**
	 * @covers PKPLocale
	 */
	public function testGetLocaleFrom3LetterIso() {
		// A locale that does not have to be disambiguated.
		self::assertEquals('en_US', Locale::getLocaleFrom3LetterIso('eng'));

		// The primary locale will be used if that helps
		// to disambiguate.
		Locale::setSupportedLocales(array('en_US' => 'English', 'pt_BR' => 'Portuguese (Brazil)', 'pt_PT' => 'Portuguese (Portugal)'));
		Locale::setPrimaryLocale('pt_BR');
		self::assertEquals('pt_BR', Locale::getLocaleFrom3LetterIso('por'));
		Locale::setPrimaryLocale('pt_PT');
		self::assertEquals('pt_PT', Locale::getLocaleFrom3LetterIso('por'));

		// If the primary locale doesn't help then use the first supported locale found.
		Locale::setPrimaryLocale('en_US');
		self::assertEquals('pt_BR', Locale::getLocaleFrom3LetterIso('por'));
		Locale::setSupportedLocales(array('en_US' => 'English', 'pt_PT' => 'Portuguese (Portugal)', 'pt_BR' => 'Portuguese (Brazil)'));
		self::assertEquals('pt_PT', Locale::getLocaleFrom3LetterIso('por'));

		// If the locale isn't even in the supported localse then use the first locale found.
		Locale::setSupportedLocales(array('en_US' => 'English'));
		self::assertEquals('pt_PT', Locale::getLocaleFrom3LetterIso('por'));

		// Unknown language.
		self::assertNull(Locale::getLocaleFrom3LetterIso('xxx'));
	}
}
?>
