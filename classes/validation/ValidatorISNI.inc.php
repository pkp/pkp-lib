<?php

/**
 * @file classes/validation/ValidatorISNI.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorISNI
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for ISNIs.
 */

import('lib.pkp.classes.validation.ValidatorRegExp');

class ValidatorISNI extends ValidatorRegExp {
	/**
	 * Constructor.
	 */
	function ValidatorISNI() {
		parent::ValidatorRegExp(self::getRegexp());
	}


	//
	// Implement abstract methods from Validator
	//
	/**
	 * @see Validator::isValid()
	 * @param $value mixed
	 * @return boolean
	 */
	function isValid($value) {
		if (!parent::isValid($value)) return false;

		$matches = $this->getMatches();
		$match = $matches[0];

		$total = 0;
		for ($i=0; $i<15; $i++) {
			$total = ($total + $match[$i]) *2;
		}
		
		$remainder = $total % 11;
		$result = (12 - $remainder) % 11;

		return ($match[15] == ($result==10 ? 'X' : $result));
	}

	//
	// Public static methods
	//
	/**
	 * Return the regex for an ISNI check. This can be called
	 * statically.
	 * @return string
	 */
	static function getRegexp() {
		return '/^(\d{15}[0-9X])$/';
	}
}

?>
