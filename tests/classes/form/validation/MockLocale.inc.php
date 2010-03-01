<?php

/**
 * @file tests/classes/form/validation/MockLocale.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_classes_form_validation
 *
 * @brief Mock implementation of the Locale class
 */

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
	 * @param $params array
	 * @param $locale string
	 * @return string
	 */
	function translate($key, $params = array(), $locale = null) {
		self::$translationKey = $key;
		return 'translated text';
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