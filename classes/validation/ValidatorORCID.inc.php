<?php

/**
 * @file classes/validation/ValidatorORCID.inc.php
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorORCID
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for ORCID iDs.
 */

import('lib.pkp.classes.validation.Validator');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorORCID extends Validator {
	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		$validator = \ValidatorFactory::make(
			['value' => $value],
			['value' => ['required', 'orcid']]
		);

		return $validator->passes();
	}
}
