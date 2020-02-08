<?php

/**
 * @file classes/validation/ValidatorRegExp.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorRegExp
 * @ingroup validation
 *
 * @brief Validation check using a regular expression.
 */

import ('lib.pkp.classes.validation.Validator');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorRegExp extends Validator {

	/** @var The regular expression to match against the field value */
	var $_regExp;

	/**
	 * Constructor.
	 * @param $regExp string the regular expression (PCRE form)
	 */
	function __construct($regExp) {
		$this->_regExp = $regExp;
	}

	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		$validator = \ValidatorFactory::make(
			['value' => $value],
			['value' => ['required', 'regex:' . $this->_regExp]]
		);

		return $validator->passes();
	}
}
