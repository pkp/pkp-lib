<?php

/**
 * @file tests/mock/env1/MockAppLocale.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AppLocale
 * @ingroup tests_mock_env1
 *
 * @brief Mock implementation of the Locale class
 */

define('LOCALE_REGISTRY_FILE', 'lib/pkp/tests/registry/locales.xml');
define('LOCALE_ENCODING', 'utf-8');

define('LOCALE_COMPONENT_APPLICATION_COMMON',	0x00000101);
define('LOCALE_COMPONENT_OJS_EDITOR',		0x00000103);

import('lib.pkp.classes.i18n.PKPLocale');

class AppLocale extends PKPLocale {
	static
		$primaryLocale = 'en_US',
		$supportedLocales = array('en_US' => 'English/America'),
		$translations = array();

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

	/**
	 * method required during setup of
	 * the PKP templating engine and application framework
	 */
	function requireComponents() {
		// do nothing
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
		if (isset(self::$translations[$key])) {
			return self::$translations[$key];
		}
		return "##$key##";
	}

	/**
	 * Setter to configure a custom
	 * primary locale for testing.
	 * @param $primaryLocale string
	 */
	function setPrimaryLocale($primaryLocale) {
		self::$primaryLocale = $primaryLocale;
	}

	/**
	 * Mocked method
	 * @return string
	 */
	function getPrimaryLocale() {
		return self::$primaryLocale;
	}

	/**
	 * Setter to configure a custom
	 * primary locale for testing.
	 * @param $supportedLocales array
	 *  example array(
	 *   'en_US' => 'English',
	 *   'de_DE' => 'German'
	 *  )
	 */
	function setSupportedLocales($supportedLocales) {
		self::$supportedLocales = $supportedLocales;
	}

	/**
	 * Mocked method
	 * @return array
	 */
	function getSupportedLocales() {
		return self::$supportedLocales;
	}

	/**
	 * Mocked method
	 * @return array
	 */
	function getSupportedFormLocales() {
		return array('en_US');
	}

	/**
	 * Set translation keys to be faked.
	 * @param $translations array
	 */
	static function setTranslations($translations) {
		self::$translations = $translations;
	}
}
?>
