<?php

/**
 * @file classes/validation/ValidatorDate.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorDate
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for email addresses.
 */

import('lib.pkp.classes.validation.ValidatorRegExp');

define('DATE_FORMAT_ISO', 0x01);

class ValidatorDate extends ValidatorRegExp {
	/**
	 * Constructor.
	 */
	function ValidatorDate($dateFormat = DATE_FORMAT_ISO) {
		parent::ValidatorRegExp(ValidatorDate::getRegexp($dateFormat));
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

		$dateMatches = $this->getMatches();
		if (isset($dateMatches['month'])) {
			if ($dateMatches['month'] >= 1 && $dateMatches['month'] <= 12) {
				if (isset($dateMatches['day'])) {
					return checkdate($dateMatches['month'], $dateMatches['day'], $dateMatches['year']);
				} else {
					return true;
				}
			} else {
				return false;
			}
		} else {
			return true;
		}
	}


	//
	// Public static methods
	//
	/**
	 * Return the regex for a date check. This can be called
	 * statically.
	 * @param $dateFormat integer one of the DATE_FORMAT_* ids.
	 * @return string
	 */
	function getRegexp($dateFormat = DATE_FORMAT_ISO) {
		switch ($dateFormat) {
			case DATE_FORMAT_ISO:
				return '/(?P<year>\d{4})(?:-(?P<month>\d{2})(?:-(?P<day>\d{2}))?)?/';
				break;

			default:
				// FIXME: Additional date formats can be
				// added to the case list as required.
				assert(false);
		}
	}
}
?>
