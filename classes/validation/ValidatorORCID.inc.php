<?php

/**
 * @file classes/validation/ValidatorORCID.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidatorORCID
 * @ingroup validation
 * @see Validator
 *
 * @brief Validation check for ORCID iDs.
 */

import('lib.pkp.classes.validation.ValidatorRegExp');

class ValidatorORCID extends ValidatorRegExp {
	/**
	 * Constructor.
	 */
	function ValidatorORCID() {
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

		// Test the check digit
		// ORCID is an extension of ISNI
		// http://support.orcid.org/knowledgebase/articles/116780-structure-of-the-orcid-identifier
		$matches = $this->getMatches();
		$orcid = $matches[1] . $matches[2] . $matches[3] . $matches[4];

		import('lib.pkp.classes.validation.ValidatorISNI');
		$validator = new ValidatorISNI();
		return $validator->isValid($orcid);
	}

	//
	// Public static methods
	//
	/**
	 * Return the regex for an ORCID check. This can be called
	 * statically.
	 * @return string
	 */
	static function getRegexp() {
		return '/^http:\/\/orcid.org\/(\d{4})-(\d{4})-(\d{4})-(\d{3}[0-9X])$/';
	}
}

?>
