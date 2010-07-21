<?php

/**
 * @file classes/citation/CitationParserService.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationParserService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Base class for parsing services implementations.
 */

// $Id$


import('citation.CitationService');

class CitationParserService extends CitationService {
	/**
	 * Take in a Citation in state CITATION_RAW or CITATION_EDITED and
	 * return a citation in state CITATION_PARSED.
	 * 
	 * NB: This implementation uses a template pattern to do common
	 * pre- and post-parsing tasks. It calls the parseInternal()
	 * function which must be implemented by sub-classes.
	 * 
	 * @param $citation Citation the citation object to be parsed.
	 * @return Citation a parsed citation or null on failure
	 */
	function &parse(&$citation) {
		// Retrieve the correct text to be parsed
		$citationString = $this->getCitationString($citation);
		
		$this->parseInternal($citationString, $citation);
		if (is_null($citation)) {
			$nullVar = null;
			return $nullVar;
		}
		
		// Trim punctuation
		$metadataElements = $citation->getNonEmptyElementsAsArray(METADATA_ELEMENT_TYPE_STRING);
		foreach($metadataElements as $elementName => $elementValue) {
			if (is_array($elementValue)) {
				foreach($elementValue as $key => $elementInstanceValue) {
					$metadataElements[$elementName][$key] =
							$this->trimPunctuation($elementInstanceValue);
				}
			} else {
				$metadataElements[$elementName] = $this->trimPunctuation($elementValue);
			}
		}
		
		$citation->setElementsFromArray($metadataElements);
		
		return $citation;
	}
	
	//
	// Protected functions to be used by sub-classes
	//
	/**
	 * Internal template function for parsing citations.
	 * Must be implemented by sub-classes.
	 * @param $citationString string the citation string to be parsed.
	 * @param $citation Citation the target citation object.
	 */
	function parseInternal($citationString, &$citation) {
		// to be implemented by sub-classes
		assert(false);
	}
	
	/**
	 * Retrieve the most appropriate full text citation string
	 * from a citation (i.e. the raw citation for a citation in
	 * state CITATION_RAW and the edited citation for a citation
	 * in state CITATION_EDITED).
	 * 
	 * NB: This also cleans the citation string for parsing. 
	 * 
	 * @param $citation Citation
	 * @return string the citation string
	 */
	function getCitationString(&$citation) {
		switch($citation->getCitationState()) {
			case CITATION_RAW:
				$citationString = $citation->getRawCitation();
				break;
				
			// Get the edited citation string for an edited,
			// parsed or looked up citation.
			default:
				$citationString = $citation->getEditedCitation();
		}
		
		assert(!empty($citationString));
		
		// Normalize whitespace (newline, tab, duplicate spaces, etc.)
		$citationString = String::regexp_replace('/[\s]+/', ' ', $citationString);
		
		return $citationString;
	}
}
?>