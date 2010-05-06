<?php

/**
 * @file classes/metadata/NlmPersonStringFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmPersonStringFilter
 * @ingroup metadata_nlm
 * @see NlmNameSchema
 *
 * @brief Filter that converts from a string
 *  to an (array of) NLM name description(s).
 */

// $Id$

import('lib.pkp.classes.filter.Filter');
import('lib.pkp.classes.metadata.MetadataDescription');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');

define('PERSON_STRING_FILTER_MULTIPLE', 0x01);
define('PERSON_STRING_FILTER_SINGLE', 0x02);

define('PERSON_STRING_FILTER_ETAL', 'et-al');

class NlmPersonStringFilter extends Filter {
	/** @var integer */
	var $_filterMode;

	/**
	 * Constructor
	 */
	function NlmPersonStringFilter($filterMode = PERSON_STRING_FILTER_SINGLE) {
		$this->_filterMode = $filterMode;
	}

	//
	// Setters and Getters
	//
	/**
	 * Get the filter mode
	 * @return integer
	 */
	function getFilterMode() {
		return $this->_filterMode;
	}

	/**
	 * Set the filter mode
	 * @param $filterMode integer
	 */
	function setFilterMode($filterMode) {
		$this->_filterMode = $filterMode;
	}


	//
	// Private helper methods
	//
	/**
	 * Check whether the given input is a valid (array of)
	 * person description(s).
	 * @param $personDescription mixed
	 * @return boolean
	 */
	function isValidPersonDescription(&$personDescription) {
		// Check the filter mode
		if (is_array($personDescription)) {
			if (!$this->_filterMode == PERSON_STRING_FILTER_MULTIPLE) return false;
			$validationArray = &$personDescription;
		} else {
			if (!$this->_filterMode == PERSON_STRING_FILTER_SINGLE) return false;
			$validationArray = array(&$personDescription);
		}

		// Validate all descriptions
		foreach($validationArray as $nameDescription) {
			// The et-al string is considered a valid person description.
			if (!(is_string($nameDescription) && $nameDescription == PERSON_STRING_FILTER_ETAL)) {
				if (!is_a($nameDescription, 'MetadataDescription')) return false;
				$metadataSchema =& $nameDescription->getMetadataSchema();
				if ($metadataSchema->getName() != 'nlm-3.0-name') return false;
				unset($metadataSchema);
			}
		}

		return true;
	}
}
?>