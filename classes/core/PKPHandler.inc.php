<?php

/**
 * @file classes/core/PKPHandler.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package core
 * @class PKPHandler
 *
 * Base request handler abstract class.
 *
 * $Id$
 */

class PKPHandler {
	/**
	 * Fallback method in case request handler does not implement index method.
	 */
	function index() {
		header('HTTP/1.0 404 Not Found');
		fatalError('404 Not Found');
	}

	/**
	 * Perform request access validation based on security settings.
	 */
	function validate() {
		if (Config::getVar('security', 'force_ssl') && Request::getProtocol() != 'https') {
			// Force SSL connections site-wide
			Request::redirectSSL();
		}
	}

	/**
	 * Delegate request handling to another handler class
	 */
	function delegate($fullClassName) {
		import($fullClassName);

		call_user_func(
			array(
				array_pop(explode('.', $fullClassName)),
				Request::getRequestedOp()
			),
			Request::getRequestedArgs()
		);
	}

	function setupTemplate() {
		Locale::requireComponents(array(
			 LOCALE_COMPONENT_PKP_COMMON,
			 LOCALE_COMPONENT_PKP_USER
		));
	}
}

?>
