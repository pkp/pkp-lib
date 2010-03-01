<?php

/**
 * @file tests/classes/citation/lookup/crossref/MockLocale.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_classes_citation_lookup_crossref
 *
 * @brief Mock implementation of the Request class
 */

class Request {
	private static
		$requestMethod;

	public static function setRequestMethod($requestMethod) {
		self::$requestMethod = $requestMethod;
	}

	public static function isPost() {
		return (self::$requestMethod == 'POST');
	}
}
?>