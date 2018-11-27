<?php

/**
 * @file classes/validation/ValidatorISSN.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorISSN
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for ISSNs.
 */

import('lib.pkp.classes.validation.Validator');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorISSN extends Validator {
	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		$validator = \ValidatorFactory::make(
			['value' => $value],
			['value' => ['required', 'issn']]
		);

		return $validator->passes();
	}
}
