<?php

/**
 * @defgroup citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Citation
 * @ingroup citation
 * @see CitationParserService
 * @see Metadata
 *
 * @brief Class representing a translatable citation
 */

// $Id$

define('CITATION_RAW', 0x01);
define('CITATION_EDITED', 0x02);
define('CITATION_PARSED', 0x03);
define('CITATION_LOOKED_UP', 0x04);

import('metadata.Metadata');

class Citation extends Metadata {
	/**
	 * Constructor.
	 * @param $genre integer one of the supported metadata genres
	 * @param $rawCitation string an unparsed citation string
	 */
	function Citation($genre = METADATA_GENRE_UNKNOWN, $rawCitation = null) {
		parent::Metadata($genre);
		$this->setRawCitation($rawCitation); // this will set state to CITATION_RAW
	}
	
	//
	// Get/set methods
	//

	/**
	 * get the citationState
	 * @param $locale string retrieve the rawCitation in this locale
	 * @return integer
	 */
	function getCitationState($locale = null) {
		return $this->getData('citationState');
	}
	
	/**
	 * set the citationState
	 * @param $citationState integer
	 * @param $locale string set the citationState for this locale
	 */
	function setCitationState($citationState, $locale = null) {
		assert(in_array($citationState, Citation::_getSupportedCitationStates()));
		$this->setData('citationState', $citationState, $locale);
		// FIXME: clean up the class (editedCitation, metadataElements) when the
		// state is reset from "parsed" or "confirmed" to "edited" or "raw".
	}

	/**
	 * get the rawCitation
	 * @param $locale string retrieve the rawCitation in this locale
	 * @return string
	 */
	function getRawCitation($locale = null) {
		return $this->getData('rawCitation', $locale);
	}
	
	/**
	 * set the rawCitation
	 * NB: This will reset the state of the citation to CITATION_RAW and
	 * the corresponding edited citation will be set to the same string.
	 * @param $rawCitation string
	 * @param $locale string set the rawCitation for this locale
	 */
	function setRawCitation($rawCitation, $locale = null) {
		// Re-set the edited citation
		$this->setEditedCitation($rawCitation, $locale);
		
		// Setting a new raw citation string will reset the
		// state of the citation to "raw". This must be done
		// after setEditedCitation() as this will set the
		// citation state to CITATION_EDITED.
		$this->setCitationState(CITATION_RAW, $locale);
		
		// Clean the raw citation
		// 1) If the string contains non-UTF8 characters, convert it to UTF-8
		if ( Config::getVar('i18n', 'charset_normalization') == 'On' && !String::utf8_compliant($rawCitation) ) {
			$rawCitation = String::utf8_normalize($rawCitation);
		}
		// 2) Strip slashes and whitespace
		$rawCitation = trim(stripslashes($rawCitation));
		
		$this->setData('rawCitation', $rawCitation, $locale);
	}
	
	/**
	 * get the editedCitation
	 * @param $locale string retrieve the editedCitation in this locale
	 * @return string
	 */
	function getEditedCitation($locale = null) {
		return $this->getData('editedCitation', $locale);
	}
	
	/**
	 * set the editedCitation
	 * NB: This will reset the state of the citation to CITATION_EDITED.
	 * @param $editedCitation string
	 * @param $locale string set the editedCitation for this locale
	 */
	function setEditedCitation($editedCitation, $locale = null) {
		// Setting a new edited citation string will reset the
		// state of the citation to "edited"
		$this->setCitationState(CITATION_EDITED, $locale);
		$this->setData('editedCitation', $editedCitation, $locale);
	}
	
	/**
	 * get the confidence score the citation parser attributed to this citation
	 * @param $locale string retrieve the parseScore in this locale
	 * @return integer
	 */
	function getParseScore($locale = null) {
		return $this->getData('parseScore', $locale);
	}
	
	/**
	 * set the confidence score of the citation parser
	 * @param $parseScore integer
	 * @param $locale string set the parseScore for this locale
	 */
	function setParseScore($parseScore, $locale = null) {
		$this->setData('parseScore', $parseScore, $locale);
	}
	
	/**
	 * get the lookup similarity score that the citation parser attributed to this citation
	 * @param $locale string retrieve the lookupScore in this locale
	 * @return integer
	 */
	function getLookupScore($locale = null) {
		return $this->getData('lookupScore', $locale);
	}
	
	/**
	 * set the lookup similarity score
	 * @param $lookupScore integer
	 * @param $locale string set the lookupScore for this locale
	 */
	function setLookupScore($lookupScore, $locale = null) {
		$this->setData('lookupScore', $lookupScore, $locale);
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