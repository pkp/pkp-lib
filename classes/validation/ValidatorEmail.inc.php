<?php

/**
 * @file classes/validation/ValidatorEmail.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmail
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for email addresses.
 */

import('lib.pkp.classes.validation.ValidatorRegExp');

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
	 * Return the regex for an email check. This can be called
	 * statically.
	 * @return string
	 */
	function getRegexp() {
		return '/^' . PCRE_EMAIL_ADDRESS . '$/i';
	}
}

?>
