<?php

/**
 * @file classes/validation/ValidatorUri.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorUri
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for URIs.
 */

import('lib.pkp.classes.validation.ValidatorRegExp');

class ValidatorUri extends ValidatorRegExp {
	/**
	 * Constructor.
	 * @param $allowedSchemes array
	 */
	function ValidatorUri($allowedSchemes = null) {
		parent::ValidatorRegExp(ValidatorUri::getRegexp($allowedSchemes));
	}

	//
	// Implement abstract methods from Validator
	//
	/**
	 * @see ValidatorRegExp::isValid()
	 * @param $value mixed
	 * @return boolean
	 */
	function isValid($value) {
		if(!parent::isValid($value)) return false;

		// Retrieve the matches from the regexp validator
		$matches = $this->getMatches();

		// Check IPv4 address validity
		if (!empty($matches[4])) {
			$parts = explode('.', $matches[4]);
			foreach ($parts as $part) {
				if ($part > 255) {
					return false;
				}
			}
		}

		return true;
	}

	//
	// Public static methods
	//
	/**
	 * Return the regex for an URI check. This can be called
	 * statically.
	 * @param $allowedSchemes
	 * @return string
	 */
	function getRegexp($allowedSchemes = null) {
		if (is_array($allowedSchemes)) {
			$schemesRegEx = '(?:(' . implode('|', $allowedSchemes) . '):)';
			$regEx = $schemesRegEx . substr(PCRE_URI, 24);
		} else {
			$regEx = PCRE_URI;
		}
		return '&^' . $regEx . '$&i';
	}
}
?>
