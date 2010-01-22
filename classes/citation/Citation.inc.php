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
	 * NB: Currently we use the NLM citation-element as
	 * underlying meta-data schema for a citation. This can
	 * be made configurable by adding a meta-data schema
	 * parameter to this constructor.
	 * @param $rawCitation string an unparsed citation string
	 */
	function Citation($rawCitation = null) {
		// Instantiate the underlying meta-data schema
		// FIXME: This should be done via plugin/user-configurable settings
		$metadataSchema = new NlmCitationSchema();
		$metadataAdapter = new NlmCitationSchemaCitationAdapter();
		$this->addSupportedMetadataSchema($metadataSchema, $metadataAdapter);

		$this->setRawCitation($rawCitation); // this will set state to CITATION_RAW
	}

	//
	// Get/set methods
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
		$previousCitationState = $this->_citationState;

		assert(in_array($previousCitationState, Citation::_getSupportedCitationStates()));
		assert(in_array($citationState, Citation::_getSupportedCitationStates()));

		// Clean up the class when the state is reset
		if ($previousCitationState > $citationState) {
			switch($citationState) {
				case CITATION_RAW:
					$this->_editedCitation = $this->_rawCitation;
				case CITATION_EDITED:
					$this->_parseScore = null;
					$statements = array();
					$this->setStatements($statements);
				case CITATION_PARSED:
					$this->_lookupScore = null;
					break;

				default:
					// unsupported citation state
					assert(false);
			}
		}

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
	 * NB: This will reset the state of the citation to CITATION_RAW and
	 * the corresponding edited citation will be implicitly set to the same
	 * string.
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

		// Setting a new raw citation string will reset the
		// state of the citation to "raw" and implicitly reset
		// the edited citation string to the same raw string.
		$this->setCitationState(CITATION_RAW);
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
	 * NB: This will reset the state of the citation to CITATION_EDITED.
	 * @param $editedCitation string
	 */
	function setEditedCitation($editedCitation) {
		// Setting a new edited citation string will reset the
		// state of the citation to "edited"
		$this->_editedCitation = $editedCitation;
		$this->setCitationState(CITATION_EDITED);
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