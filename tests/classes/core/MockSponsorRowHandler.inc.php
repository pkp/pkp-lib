<?php
/**
 * @file tests/classes/core/MockSponsorRowHandler.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SponsorRowHandler
 * @ingroup tests
 * @see PKPComponentRouterTest
 *
 * @brief Mock implementation of the SponsorRowHandler class for the PKPComponentRouterTest
 */

// $Id$


import('classes.handler.PKPHandler');

/**
 * This class does not extend any other class so that
 * we can test whether the router rejects handlers that
 * do not extend PKPHandler.
 */
class SponsorRowHandler {
	function fetch() {
		// This mock method does nothing
	}
}
?>