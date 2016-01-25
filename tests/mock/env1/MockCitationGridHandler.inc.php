<?php
/**
 * @file tests/mock/env1/MockCitationGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationGridHandler
 * @ingroup tests_mock_env1
 * @see PKPComponentRouterTest
 *
 * @brief Mock implementation of the CitationGridHandler class for the PKPComponentRouterTest
 */

import('lib.pkp.classes.handler.PKPHandler');

// Define a test role.
if (!defined('ROLE_ID_AUTHOR')) {
	define('ROLE_ID_AUTHOR', 0x00010000);
}

class CitationGridHandler extends PKPHandler {
	private $_fetchArgs;

	function CitationGridHandler() {
		$this->_checks = array();
		// Make sure that the parent constructor
		// will not be called.

		// Assign operations to roles.
		$this->addRoleAssignment(ROLE_ID_AUTHOR, 'fetch');
	}

	function authorize() {
		return true;
	}

	function fetchGrid() {
		// Log the call to the fetch method
		assert(is_null($this->_fetchArgs));
		$this->_fetchArgs = func_get_args();
	}

	function &getFetchArgs() {
		// Return the arguments that were passed
		// to the fetch call (if any)
		return $this->_fetchArgs;
	}
}
?>
