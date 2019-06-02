<?php

/**
 * @file classes/validation/ValidatorEmail.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmail
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for email addresses.
 */

import('lib.pkp.classes.validation.Validator');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorEmail extends Validator {
	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		$validator = \ValidatorFactory::make(
			['value' => $value],
			['value' => ['required', 'email_or_localhost']]
		);

		return $validator->passes();
	}
}
