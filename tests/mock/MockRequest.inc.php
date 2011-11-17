<?php

/**
 * @file tests/classes/form/validation/MockRequest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Request
 * @ingroup tests_classes_form_validation
 *
 * @brief Mock implementation of the Request class
 */


import('lib.pkp.classes.core.PKPRequest');

class Request extends PKPRequest {
	private static
		$requestMethod;

	public function setRequestMethod($requestMethod) {
		self::$requestMethod = $requestMethod;
	}

	public function isPost() {
		return (self::$requestMethod == 'POST');
	}
}
?>