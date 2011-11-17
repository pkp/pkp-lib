<?php
/**
 * @file tests/classes/core/MockValidation.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Validation
 * @ingroup tests_classes_core
 * @see PKPPageRouterTest
 *
 * @brief Mock implementation of the Validation class for the PKPPageRouterTest
 */


class Validation {
	static $_isLoggedIn = false;

	function isLoggedIn() {
		return Validation::$_isLoggedIn;
	}

	function setIsLoggedIn($isLoggedIn) {
		Validation::$_isLoggedIn = $isLoggedIn;
	}
}
?>
