<?php

/**
 * @defgroup citation_parser_parscit
 */

/**
 * @file classes/citation/parser/parscit/ParscitRawCitationNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitRawCitationNlmCitationSchemaFilter
 * @ingroup citation_parser_parscit
 *
 * @brief Parsing filter implementation that uses the Parscit web service.
 *
 */

// $Id$

import('citation.NlmCitationSchemaFilter');

define('PARSCIT_WEBSERVICE', 'http://aye.comp.nus.edu.sg/parsCit/parsCit.cgi');

class ParscitRawCitationNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/*
	 * Constructor
	 */
	function ParscitRawCitationNlmCitationSchemaFilter() {
		parent::NlmCitationSchemaFilter();
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
		return parent::supports($input, $output, true);
	}

	/**
	 * @see Filter::process()
	 * @param $citationString string
	 * @return MetadataDescription
	 */
	function &process($citationString) {
		$nullVar = null;
		$queryParams = array(
			'textlines' => $citationString
		);
		// Parscit web form - the result is (mal-formed) HTML
		if (is_null($result = $this->callWebService(PARSCIT_WEBSERVICE, $queryParams, XSL_TRANSFORMER_DOCTYPE_STRING))) return $nullVar;

		// Screen-scrape the tagged portion and turn it into XML
		$xmlResult = String::regexp_replace('/.*<algorithm[^>]+>(.*)<\/algorithm>.*/s', '\1', html_entity_decode($result));
		$xmlResult = String::regexp_replace('/&/', '&amp;', $xmlResult);

		// Transform the result into an array of meta-data
		if (is_null($metadata = $this->transformWebServiceResults($xmlResult, dirname(__FILE__).DIRECTORY_SEPARATOR.'parscit.xsl'))) return $nullVar;

		// Extract a publisher from the place string if possible
		$metadata =& $this->fixPublisherNameAndLocation($metadata);

		return $this->addMetadataArrayToNlmCitationDescription($metadata);
	}
}
?>