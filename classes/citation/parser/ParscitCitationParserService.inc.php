<?php

/**
 * @file classes/citation/ParscitCitationParserService.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitCitationParserService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Parsing service implementation that uses the Parscit web service.
 *
 */

// $Id$

import('citation.CitationParserService');

define('CITATION_PARSER_PARSCIT_BASEURL', 'http://aye.comp.nus.edu.sg/parsCit/parsCit.cgi?textlines=');

class ParscitCitationParserService extends CitationParserService {
	/**
	 * @see CitationParserService::parseInternal()
	 * @param $citationString string
	 * @param $citation Citation
	 */
	function parseInternal($citationString, &$citation) {
		// Parscit web form - the result is (mal-formed) HTML
		if (is_null($result = $this->callWebService(CITATION_PARSER_PARSCIT_BASEURL.urlencode($citationString)))) {
			// Catch web service error condition
			$citation = null;
			return;
		}
		
		// Screen-scrape the tagged portion and turn it into XML
		$xmlResult = String::regexp_replace('/.*<algorithm[^>]+>(.*)<\/algorithm>.*/s', '\1', html_entity_decode($result));
		$xmlResult = String::regexp_replace('/&/', '&amp;', $xmlResult);
		
		// Transform the result into an array of meta-data
		$metadata = $this->transformWebServiceResults($xmlResult, 'parser'.DIRECTORY_SEPARATOR.'parscit.xsl');

		// Extract a publisher from the place string if possible
		$this->fixPlaceAndPublisher($metadata);
		
		if (!$citation->setElementsFromArray($metadata)) {
			// Catch invalid metadata error condition
			$citation = null;
			return;
		}
	}
}
?>