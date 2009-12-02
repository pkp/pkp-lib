<?php
/**
 * @file tests/classes/core/MockSponsorCellHandler.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SponsorCellHandler
 * @ingroup tests
 * @see PKPComponentRouterTest
 *
 * @brief Mock implementation of the SponsorCellHandler class for the PKPComponentRouterTest
 */

// $Id$


import('classes.handler.PKPHandler');

class SponsorCellHandler extends PKPHandler {
	private $_fetchArgs;

	function SponsorCellHandler() {
		// Make sure that the parent constructor
		// will not be called.
	}

	function fetch() {
		// Log the call to the fetch method
		assert(is_null($this->_fetchArgs));
		$this->_fetchArgs =& func_get_args();
	}

	function &getFetchArgs() {
		// Return the arguments that were passed
		// to the fetch call (if any)
		return $this->_fetchArgs;
	}
}
?>