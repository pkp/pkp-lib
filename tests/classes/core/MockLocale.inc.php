<?php

/**
 * @file tests/classes/submission/MockLocale.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_classes_core
 * @see SubmissionTest
 *
 * @brief Mock implementation of the Locale class for the SubmissionTest
 */

// $Id$


class Locale {
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
		return 'de_DE';
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
		return array('de_DE', 'en_US');
	}
}
?>