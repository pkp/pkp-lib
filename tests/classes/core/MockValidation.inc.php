<?php
/**
 * @file tests/classes/core/MockValidation.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Validation
 * @ingroup tests
 * @see PKPPageRouterTest
 *
 * @brief Mock implementation of the Validation class for the PKPPageRouterTest
 */

// $Id$


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