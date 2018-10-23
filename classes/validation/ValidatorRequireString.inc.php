<?php
/**
 * @file classes/validation/ValidatorRequireString.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorRequireString
 * @ingroup validation
 *
 * @brief Validation check that requires a non-empty string in the value.
 */
import ('lib.pkp.classes.validation.Validator');

class ValidatorRequireString extends Validator {
	/**
	 * @copydoc Validator::isValid()
	 */
	public function isValid($value) {
		return is_string($value) && (trim($value) === '0' || !empty($value));
	}
}
