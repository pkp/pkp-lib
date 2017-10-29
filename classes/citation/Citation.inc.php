<?php

/**
 * @defgroup citation Citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Citation
 * @ingroup citation
 *
 * @brief Class representing a citation (bibliographic reference)
 */


define('CITATION_RAW', 0x01);
define('CITATION_CHECKED', 0x02);
define('CITATION_PARSED', 0x03);
define('CITATION_LOOKED_UP', 0x04);
define('CITATION_APPROVED', 0x05);

import('lib.pkp.classes.core.DataObject');

class Citation extends DataObject {
	/** @var int citation state (raw, edited, parsed, looked-up) */
	var $_citationState = CITATION_RAW;

	/**
	 * @var array errors that occurred while
	 *  checking or filtering the citation.
	 */
	var $_errors = array();


	/**
	 * Constructor.
	 * @param $rawCitation string an unparsed citation string
	 */
	function __construct($rawCitation = null) {
		parent::__construct();
		$this->setRawCitation($rawCitation); // this will set state to CITATION_RAW
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the citationState
	 * @return integer
	 */
	function getCitationState() {
		return $this->_citationState;
	}

	/**
	 * Set the citationState
	 * @param $citationState integer
	 */
	function setCitationState($citationState) {
		assert(in_array($citationState, Citation::_getSupportedCitationStates()));
		$this->_citationState = $citationState;
	}

	/**
	 * Get the association type
	 * @return integer
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set the association type
	 * @param $assocType integer
	 */
	function setAssocType($assocType) {
		$this->setData('assocType', $assocType);
	}

	/**
	 * Get the association id
	 * @return integer
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set the association id
	 * @param $assocId integer
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}

	/**
	 * Add a checking error
	 * @param $errorMessage string
	 */
	function addError($errorMessage) {
		$this->_errors[] = $errorMessage;
	}

	/**
	 * Get all checking errors
	 * @return array
	 */
	function getErrors() {
		return $this->_errors;
	}


	/**
	 * Get the rawCitation
	 * @return string
	 */
	function getRawCitation() {
		return $this->getData('rawCitation');
	}

	/**
	 * Set the rawCitation
	 * @param $rawCitation string
	 */
	function setRawCitation($rawCitation) {
		$rawCitation = $this->_cleanCitationString($rawCitation);

		$this->setData('rawCitation', $rawCitation);
	}

	/**
	 * Get the sequence number
	 * @return integer
	 */
	function getSequence() {
		return $this->getData('seq');
	}

	/**
	 * Set the sequence number
	 * @param $seq integer
	 */
	function setSequence($seq) {
		$this->setData('seq', $seq);
	}

	//
	// Private methods
	//
	/**
	 * Return supported citation states
	 * @return array supported citation states
	 */
	static function _getSupportedCitationStates() {
		static $_supportedCitationStates = array(
			CITATION_RAW,
			CITATION_CHECKED,
			CITATION_PARSED,
			CITATION_LOOKED_UP,
			CITATION_APPROVED
		);
		return $_supportedCitationStates;
	}

	/**
	 * Take a citation string and clean/normalize it
	 * @param $citationString string
	 * @return string
	 */
	function _cleanCitationString($citationString) {
		// 1) If the string contains non-UTF8 characters, convert it to UTF-8
		if (Config::getVar('i18n', 'charset_normalization') && !PKPString::utf8_compliant($citationString)) {
			$citationString = PKPString::utf8_normalize($citationString);
		}
		// 2) Strip slashes and whitespace
		$citationString = trim(stripslashes($citationString));

		// 3) Normalize whitespace
		$citationString = PKPString::regexp_replace('/[\s]+/', ' ', $citationString);

		return $citationString;
	}
}
?>
