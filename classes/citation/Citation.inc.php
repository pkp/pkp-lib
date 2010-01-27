<?php

/**
 * @defgroup citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Citation
 * @ingroup citation
 * @see CitationParserService
 * @see MetadataDescription
 *
 * @brief Class representing a citation (bibliographic reference)
 */

// $Id$

define('CITATION_RAW', 0x01);
define('CITATION_EDITED', 0x02);
define('CITATION_PARSED', 0x03);
define('CITATION_LOOKED_UP', 0x04);

import('core.DataObject');
import('metadata.NlmCitationSchema');
import('metadata.NlmCitationSchemaCitationAdapter');

class Citation extends DataObject {
	/** @var integer */
	var $_assocType;

	/** @var integer */
	var $_assocId;

	/** @var int citation state (raw, edited, parsed, looked-up) */
	var $_citationState = CITATION_RAW;

	/** @var string */
	var $_rawCitation;

	/** @var string */
	var $_editedCitation;

	/** @var float */
	var $_parseScore;

	/** @var float */
	var $_lookupScore;

	/**
	 * Constructor.
	 * @param $rawCitation string an unparsed citation string
	 */
	function Citation($rawCitation = null) {
		parent::DataObject();

		// Add NLM meta-data adapter.
		// FIXME: This will later be done via plugin/user-configurable settings,
		// see comment in DataObject::DataObject().
		$metadataAdapter = new NlmCitationSchemaCitationAdapter();
		$this->addSupportedMetadataAdapter($metadataAdapter);

		$this->setRawCitation($rawCitation); // this will set state to CITATION_RAW
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the association type
	 * @return integer
	 */
	function getAssocType() {
		return $this->_assocType;
	}

	/**
	 * Set the association type
	 * @param $assocType integer
	 */
	function setAssocType($assocType) {
		$this->_assocType = $assocType;
	}

	/**
	 * Get the association id
	 * @return integer
	 */
	function getAssocId() {
		return $this->_assocId;
	}

	/**
	 * Set the association id
	 * @param $assocId integer
	 */
	function setAssocId($assocId) {
		$this->_assocId = $assocId;
	}

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
	 * Get the rawCitation
	 * @return string
	 */
	function getRawCitation() {
		return $this->_rawCitation;
	}

	/**
	 * Set the rawCitation
	 * @param $rawCitation string
	 */
	function setRawCitation($rawCitation) {
		// Clean the raw citation
		// 1) If the string contains non-UTF8 characters, convert it to UTF-8
		if (Config::getVar('i18n', 'charset_normalization') && !String::utf8_compliant($rawCitation)) {
			$rawCitation = String::utf8_normalize($rawCitation);
		}
		// 2) Strip slashes and whitespace
		$rawCitation = trim(stripslashes($rawCitation));

		$this->_rawCitation = $rawCitation;
	}

	/**
	 * Get the editedCitation
	 * @return string
	 */
	function getEditedCitation() {
		return $this->_editedCitation;
	}

	/**
	 * Set the editedCitation
	 * @param $editedCitation string
	 */
	function setEditedCitation($editedCitation) {
		$this->_editedCitation = $editedCitation;
	}

	/**
	 * Get the confidence score the citation parser attributed to this citation
	 * @return integer
	 */
	function getParseScore() {
		return $this->_parseScore;
	}

	/**
	 * Set the confidence score of the citation parser
	 * @param $parseScore integer
	 */
	function setParseScore($parseScore) {
		$this->_parseScore = $parseScore;
	}

	/**
	 * Get the lookup similarity score that the citation parser attributed to this citation
	 * @return integer
	 */
	function getLookupScore() {
		return $this->_lookupScore;
	}

	/**
	 * Set the lookup similarity score
	 * @param $lookupScore integer
	 */
	function setLookupScore($lookupScore) {
		$this->_lookupScore = $lookupScore;
	}


	//
	// Private methods
	//
	/**
	 * Return supported citation states
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported citation states
	 */
	function _getSupportedCitationStates() {
		static $_supportedCitationStates = array(
			CITATION_RAW,
			CITATION_EDITED,
			CITATION_PARSED,
			CITATION_LOOKED_UP
		);
		return $_supportedCitationStates;
	}
}
?>