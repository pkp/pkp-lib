<?php

/**
 * @file tests/DatabaseTestCase.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DatabaseTestCase
 * @ingroup tests
 *
 * @brief Base class for unit tests that require database support.
 *        The schema TestName.setUp.xml will be installed before each
 *        individual test case (if present). The schema TestName.tearDown.xml may
 *        be used to clean up after each test case.
 */


import('lib.pkp.tests.PKPTestCase');

abstract class DatabaseTestCase extends PKPTestCase {
	protected function setUp() {
		// Rather than using "include_once()", ADOdb uses
		// a global variable to maintain the information
		// whether its library has been included before (wtf!).
		// This causes problems with PHPUnit as PHPUnit will
		// delete all global state between two consecutive
		// tests to isolate tests from each other.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}
	}
}
?>
