<?php

/**
 * @file classes/validation/ValidatorDate.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorDate
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for dates.
 */

import('lib.pkp.classes.validation.Validator');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorDate extends Validator {
	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		$validator = \ValidatorFactory::make(
			['value' => $value],
			['value' => ['required', 'date']]
		);

		return $validator->passes();
	}
}
