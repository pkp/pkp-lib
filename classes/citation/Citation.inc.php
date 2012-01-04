<?php

/**
 * @defgroup citation
 */

/**
 * @file classes/citation/Citation.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
import('metadata.nlm.NlmCitationSchema');
import('metadata.nlm.NlmCitationSchemaCitationAdapter');

class Citation extends DataObject {
	/** @var int citation state (raw, edited, parsed, looked-up) */
	var $_citationState = CITATION_RAW;

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
	 * Get the editedCitation
	 * @return string
	 */
	function getEditedCitation() {
		return $this->getData('editedCitation');
	}

	/**
	 * Set the editedCitation
	 * @param $editedCitation string
	 */
	function setEditedCitation($editedCitation) {
		$editedCitation = $this->_cleanCitationString($editedCitation);

		$this->setData('editedCitation', $editedCitation);
	}

	/**
	 * Get the confidence score the citation parser attributed to this citation
	 * @return integer
	 */
	function getParseScore() {
		return $this->getData('parseScore');
	}

	/**
	 * Set the confidence score of the citation parser
	 * @param $parseScore integer
	 */
	function setParseScore($parseScore) {
		$this->setData('parseScore', $parseScore);
	}

	/**
	 * Get the lookup similarity score that the citation parser attributed to this citation
	 * @return integer
	 */
	function getLookupScore() {
		return $this->getData('lookupScore');
	}

	/**
	 * Set the lookup similarity score
	 * @param $lookupScore integer
	 */
	function setLookupScore($lookupScore) {
		$this->setData('lookupScore', $lookupScore);
	}

	/**
	 * Returns all properties of this citation. The returned
	 * array contains the name spaces as key and the property
	 * list as values.
	 * @return array
	 */
	function &getNamespacedMetadataProperties() {
		$metadataAdapters =& $this->getSupportedMetadataAdapters();
		$metadataProperties = array();
		foreach($metadataAdapters as $metadataAdapter) {
			$metadataSchema =& $metadataAdapter->getMetadataSchema();
			$metadataProperties[$metadataSchema->getNamespace()] = $metadataSchema->getProperties();
		}
		return $metadataProperties;
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

	/**
	 * Take a citation string and clean/normalize it
	 * @param $citationString string
	 * @return string
	 */
	function _cleanCitationString($citationString) {
		// 1) If the string contains non-UTF8 characters, convert it to UTF-8
		if (Config::getVar('i18n', 'charset_normalization') && !String::utf8_compliant($citationString)) {
			$citationString = String::utf8_normalize($citationString);
		}
		// 2) Strip slashes and whitespace
		$citationString = trim(stripslashes($citationString));

		// 3) Normalize whitespace
		$citationString = String::regexp_replace('/[\s]+/', ' ', $citationString);

		return $citationString;
	}
}
?>