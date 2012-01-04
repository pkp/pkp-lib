<?php

/**
 * @file classes/metadata/NlmPersonStringFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmPersonStringFilter
 * @ingroup metadata_nlm
 * @see NlmNameSchema
 *
 * @brief Filter that converts from a string
 *  to an (array of) NLM name description(s).
 */


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
		parent::Filter();
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

		// A change in the filter mode also
		// changes the transformation type.
		$supportedTransformations = $this->getSupportedTransformations();
		assert(count($supportedTransformations) == 1);
		$this->setTransformationType($supportedTransformations[0][0], $supportedTransformations[0][1]);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformations()
	 * @param $singleMode array the transformation in single mode
	 * @param $multiMode array the transformation in multi mode
	 * @return array the supported transformations depending on the filter mode
	 */
	function getSupportedTransformations($singleMode, $multiMode) {
		switch($this->getFilterMode()) {
			case PERSON_STRING_FILTER_SINGLE:
				return array($singleMode);

			case PERSON_STRING_FILTER_MULTIPLE:
				return array($multiMode);

			default:
				return array($singleMode, $multiMode);
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Remove et-al entries from input/output which are valid but do not
	 * conform to the canonical transformation type definition.
	 * @param $personDescriptions mixed
	 * @return array|boolean false if more than one et-al string was found
	 *  otherwise the filtered person description list.
	 *
	 * NB: We cannot pass person descriptions by reference otherwise
	 * we'd alter our data.
	 */
	function &removeEtAlEntries($personDescriptions) {
		// Et-al is only allowed in multi-mode
		assert($this->getFilterMode() == PERSON_STRING_FILTER_MULTIPLE && is_array($personDescriptions));

		// Remove et-al strings
		$resultArray = array_filter($personDescriptions, create_function('$pd', 'return is_a($pd, "MetadataDescription");'));

		// There can be exactly one et-al string
		if (count($resultArray) < count($personDescriptions)-1) return false;

		return $resultArray;
	}
}
?>
