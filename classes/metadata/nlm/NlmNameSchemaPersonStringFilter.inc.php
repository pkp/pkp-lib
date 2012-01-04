<?php

/**
 * @file classes/metadata/NlmNameSchemaPersonStringFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	/** @var string */
	var $_template;

	/** @var string */
	var $_delimiter;

	/**
	 * Constructor
	 * @param $filterMode integer
	 * @param $template string default: DRIVER guidelines 2.0 name template
	 *  Possible template variables are %surname%, %suffix%, %prefix%, %initials%, %firstname%
	 */
	function NlmNameSchemaPersonStringFilter($filterMode = PERSON_STRING_FILTER_SINGLE, $template = '%surname%%suffix%,%initials% (%firstname%)%prefix%', $delimiter = '; ') {
		assert(!empty($template) && is_string($template));
		$this->_template = $template;
		assert(is_string($delimiter));
		$this->_delimiter = $delimiter;

		parent::NlmPersonStringFilter($filterMode);
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the output template
	 * @return string
	 */
	function getTemplate() {
		return $this->_template;
	}

	/**
	 * Set the output template
	 * @param $template string
	 */
	function setTemplate($template) {
		$this->_template = $template;
	}

	/**
	 * Get the author delimiter (for multiple mode)
	 * @return string
	 */
	function getDelimiter() {
		return $this->_delimiter;
	}

	/**
	 * Set the author delimiter (for multiple mode)
	 * @param $delimiter string
	 */
	function setDelimiter($delimiter) {
		$this->_delimiter = $delimiter;
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Check input type
		if (!$this->isValidPersonDescription($input)) return false;

		// Check output type
		if (is_null($output)) return true;
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
		$personString = implode($this->getDelimiter(), $personDescriptionStrings);
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
		$nameVars['%surname%'] = (string)$personDescription->getStatement('surname');

		$givenNames = $personDescription->getStatement('given-names');
		$nameVars['%firstname%'] = $nameVars['%initials%'] = '';
		if(is_array($givenNames) && count($givenNames)) {
			if (String::strlen($givenNames[0]) > 1) {
				$nameVars['%firstname%'] = array_shift($givenNames);
			}
			foreach($givenNames as $givenName) {
				$nameVars['%initials%'] .= String::substr($givenName, 0, 1).'.';
			}
		}
		if (!empty($nameVars['%initials%'])) $nameVars['%initials%'] = ' '.$nameVars['%initials%'];

		$nameVars['%prefix%'] = (string)$personDescription->getStatement('prefix');
		if (!empty($nameVars['%prefix%'])) $nameVars['%prefix%'] = ' '.$nameVars['%prefix%'];
		$nameVars['%suffix%'] = (string)$personDescription->getStatement('suffix');
		if (!empty($nameVars['%suffix%'])) $nameVars['%suffix%'] = ' '.$nameVars['%suffix%'];

		// Fill placeholders in person template.
		$personString = str_replace(array_keys($nameVars), array_values($nameVars), $this->getTemplate());

		// Remove empty brackets and trailing/leading whitespace
		$personString = trim(str_replace('()', '', $personString));

		return $personString;
	}
}
?>