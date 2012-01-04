<?php

/**
 * @file classes/validation/ValidatorEmail.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmail
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for email addresses.
 */

import('validation.ValidatorRegExp');

class ValidatorEmail extends ValidatorRegExp {
	/**
	 * Constructor.
	 */
	function ValidatorEmail() {
		parent::ValidatorRegExp(ValidatorEmail::getRegexp());
	}


	//
	// Public static methods
	//
	/**
	 * @see ValidatorUri::getRegexp()
	 * @return string
	 */
	function getRegexp() {
		return '/^' . PCRE_EMAIL_ADDRESS . '$/i';
	}
}

?>
