<?php

/**
 * @file classes/metadata/NlmNameSchemaPersonStringFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmNameSchemaPersonStringFilter
 * @ingroup metadata_nlm
 * @see NlmNameSchema
 *
 * @brief Filter that converts from NLM name to
 *  a string.
 */

// $Id$

import('metadata.nlm.NlmPersonStringFilter');

class NlmNameSchemaPersonStringFilter extends NlmPersonStringFilter {
	/** @var integer */
	var $_filterMode;

	/**
	 * Constructor
	 */
	function PersonStringNlmNameSchemaFilter($filterMode = PERSON_STRING_FILTER_SINGLE) {
		parent::NlmPersonStringFilter($filterMode);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		return $this->isValidPersonDescription($input);
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		return is_string($output);
	}

	/**
	 * @see Filter::process()
	 * @param $input mixed a(n array of) MetadataDescription(s)
	 * @return string
	 */
	function &process(&$input) {
		switch ($this->getFilterMode()) {
			case PERSON_STRING_FILTER_MULTIPLE:
				$personDescription = $this->_flattenPersonDescriptions($input);
				break;

			case PERSON_STRING_FILTER_SINGLE:
				$personDescription = $this->_flattenPersonDescription($input);
				break;

			default:
				assert(false);
		}

		return $personDescription;
	}

	//
	// Private helper methods
	//
	/**
	 * Transform an NLM name description array to a person string.
	 * NB: We use ; as name separator.
	 * @param $personDescriptions array an array of MetadataDescriptions
	 * @return string
	 */
	function _flattenPersonDescriptions(&$personDescriptions) {
		assert(is_array($personDescriptions));
		$personDescriptionStrings = array_map(array($this, '_flattenPersonDescription'), $personDescriptions);
		$personString = implode('; ', $personDescriptionStrings);
		return $personString;
	}

	/**
	 * Transform a single NLM name description to a person string.
	 * NB: We use the style: surname suffix, initials (first-name) prefix
	 * which is relatively easy to parse back.
	 * @param $personDescription MetadataDescription
	 * @return string
	 */
	function _flattenPersonDescription(&$personDescription) {
		$surname = (string)$personDescription->getStatement('surname');

		$givenNames = $personDescription->getStatement('given-names');
		$firstName = $initials = '';
		if(is_array($givenNames) && count($givenNames)) {
			$firstName = array_shift($givenNames);
			foreach($givenNames as $givenName) {
				$initials .= String::substr($givenName, 0, 1).'.';
			}
		}
		if (!empty($initials)) $initials = ' '.$initials;
		if (!empty($firstName)) $firstName = ' ('.$firstName.')';

		$prefix = (string)$personDescription->getStatement('prefix');
		if (!empty($prefix)) $prefix = ' '.$prefix;
		$suffix = (string)$personDescription->getStatement('suffix');
		if (!empty($suffix)) $suffix = ' '.$suffix;

		$personString = $surname.$suffix.','.$initials.$firstName.$prefix;
		return $personString;
	}
}
?>