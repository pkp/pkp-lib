<?php
/**
 * @file tests/classes/core/MockCitationGridHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationGridHandler
 * @ingroup tests_classes_core
 * @see PKPComponentRouterTest
 *
 * @brief Mock implementation of the CitationGridHandler class for the PKPComponentRouterTest
 */

import('lib.pkp.classes.handler.PKPHandler');

class CitationGridHandler extends PKPHandler {
	private $_fetchArgs;

	function CitationGridHandler() {
		$this->_checks = array();
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

	function privateMethod() {
		// This method is not in the remote operations
		// list and should therefore not be granted remote
		// access.
		assert(false);
	}

	function getRemoteOperations() {
		return array('fetch');
	}
}
?>