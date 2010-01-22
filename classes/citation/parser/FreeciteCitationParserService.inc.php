<?php

/**
 * @file classes/citation/FreeciteCitationParserService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FreeciteCitationParserService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Parsing service implementation that uses the Freecite web service.
 *
 */

// $Id$

import('citation.CitationParserService');

define('CITATION_PARSER_FREECITE_BASEURL', 'http://freecite.library.brown.edu/citations/create');

class FreeciteCitationParserService extends CitationParserService {
	/**
	 * @see CitationParserService::parseInternal()
	 * @param $citationString string
	 * @param $citation Citation
	 */
	function parseInternal($citationString, &$citation) {
		// Freecite requires a post request
		$postData = array('citation' => $citationString);
		if (is_null($xmlResult = $this->callWebService(CITATION_PARSER_FREECITE_BASEURL, $postData))) {
			// Catch web service error condition
			$citation = null;
			return;
		}
		
		// Transform the result into an array of meta-data
		$metadata = $this->transformWebServiceResults($xmlResult, 'parser'.DIRECTORY_SEPARATOR.'freecite.xsl');
		
		// Extract a publisher from the place string if possible
		$this->fixPlaceAndPublisher($metadata);
		
		// Convert article title to book title for dissertations
		if (isset($metadata['genre']) && $metadata['genre'] == METADATA_GENRE_DISSERTATION && isset($metadata['articleTitle'])) {
			$metadata['bookTitle'] = $metadata['articleTitle'];
			unset($metadata['articleTitle']);
		}
		
		unset($metadata['raw_string']);
		
		if (!$citation->setElementsFromArray($metadata)) {
			// Catch invalid metadata error condition
			$citation = null;
			return;
		}
	}
}
?>