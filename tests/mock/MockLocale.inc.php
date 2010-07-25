<?php

/**
 * @file tests/mock/MockLocale.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_mock
 *
 * @brief Mock implementation of the Locale class
 */

define('LOCALE_ENCODING', 'utf-8');


// PKP locale components
define('LOCALE_COMPONENT_PKP_COMMON',		0x00000001);
define('LOCALE_COMPONENT_PKP_ADMIN',		0x00000002);
define('LOCALE_COMPONENT_PKP_INSTALLER',	0x00000003);
define('LOCALE_COMPONENT_PKP_MANAGER',		0x00000004);
define('LOCALE_COMPONENT_PKP_READER',		0x00000005);
define('LOCALE_COMPONENT_PKP_SUBMISSION',	0x00000006);
define('LOCALE_COMPONENT_PKP_USER',		0x00000007);
define('LOCALE_COMPONENT_PKP_GRID', 	0x00000008);


class Locale {
	private static
		$translationKey;

	/*
	 * method required during setup of
	 * the PKP application framework
	 */
	function initialize() {
		// do nothing
	}

	/*
	 * method required during setup of
	 * the PKP application framework
	 * @return string test locale
	 */
	function getLocale() {
		return 'en_US';
	}

	/*
	 * method required during setup of
	 * the PKP application framework
	 */
	function registerLocaleFile($locale, $filename, $addToTop = false) {
		// do nothing
	}

	/*
	 * method required during setup of
	 * the PKP templating engine and application framework
	 */
	function requireComponents($components, $locale = null) {
		// do nothing
	}

	/**
	 * method required in PKPTemplateManager
	 */
	function getLocaleStyleSheet($locale) {
		return null;
	}

	/**
	 * Mocked method
	 * @return array a test array of locales
	 */
	function getLocalePrecedence() {
		return array('en_US', 'fr_FR');
	}

	/**
	 * Mocked method
	 * @param $key string
	 * @param $params array named substitution parameters
	 * @param $locale string the locale to use
	 * @return string
	 */
	function translate($key, $params = array(), $locale = null) {
		self::$translationKey = $key;
		return 'translated string';
	}

	/**
	 * Mocked method
	 * @return string
	 */
	function getPrimaryLocale() {
		return 'en_US';
	}

	/**
	 * Mocked method
	 * @return array
	 */
	function getAllLocales() {
		return array('en_US' => 'English/America');
	}

	/**
	 * An internal function that allows us to inpect
	 * the translation key that was passed to the
	 * translate() method.
	 * @return string
	 */
	static function getTestedTranslationKey() {
		return self::$translationKey;
	}
}
?>