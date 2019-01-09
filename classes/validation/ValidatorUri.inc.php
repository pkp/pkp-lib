<?php

/**
 * @file classes/validation/ValidatorUrI.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorUrl
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for URLs with an additional check for ipv4 syntax and
 *  the ability to restrict to specific schemes (ftp, etc)
 */

import ('lib.pkp.classes.validation.ValidatorUrl');
import('lib.pkp.classes.validation.ValidatorFactory');

class ValidatorUri extends ValidatorUrl {
	/** @var array */
	public $allowedSchemes = [];

	/**
	 * Constructor.
	 * @param $allowedSchemes array
	 */
	function __construct($allowedSchemes = null) {
		$this->allowedSchemes = $allowedSchemes;
	}

	/**
	 * @copydoc Validator::isValid()
	 */
	function isValid($value) {
		if (!parent::isValid($value)) {
			return false;
		}

		if (!empty($this->allowedSchemes)) {
			$isAllowed = false;
			foreach ($this->allowedSchemes as $scheme) {
				if (substr($value, 0, strlen($scheme)) === $scheme) {
					$isAllowed = true;
					break;
				}
			}
		}

		return $isAllowed;
	}
}
