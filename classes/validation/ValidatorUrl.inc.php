<?php

/**
 * @file classes/validation/ValidatorUrl.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorUrl
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for URLs.
 */

import('lib.pkp.classes.validation.ValidatorUri');

class ValidatorUrl extends ValidatorUri {
	/**
	 * Constructor.
	 */
	function ValidatorUrl() {
		parent::ValidatorUri(ValidatorUrl::_getAllowedSchemes());
	}

	//
	// Public static methods
	//
	/**
	 * @see ValidatorUri::getRegexp()
	 * @return string
	 */
	static function getRegexp() {
		return parent::getRegexp(ValidatorUrl::_getAllowedSchemes());
	}

	//
	// Private static methods
	//
	/**
	 * Return allowed schemes
	 * @return array
	 */
	static function _getAllowedSchemes() {
		return array('http', 'https', 'ftp');
	}
}

?>
