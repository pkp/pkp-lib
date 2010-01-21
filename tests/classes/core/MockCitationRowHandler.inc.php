<?php
/**
 * @file tests/classes/core/MockCitationRowHandler.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationRowHandler
 * @ingroup tests_classes_core
 * @see PKPComponentRouterTest
 *
 * @brief Mock implementation of the CitationRowHandler class for the PKPComponentRouterTest
 */

// $Id$


import('classes.handler.PKPHandler');

/**
 * This class does not extend any other class so that
 * we can test whether the router rejects handlers that
 * do not extend PKPHandler.
 */
class CitationRowHandler {
	function fetch() {
		// This mock method does nothing
	}
}
?>