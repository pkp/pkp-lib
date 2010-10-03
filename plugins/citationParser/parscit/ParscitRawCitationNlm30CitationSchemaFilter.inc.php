<?php

/**
 * @defgroup citation_parser_parscit
 */

/**
 * @file classes/citation/parser/parscit/ParscitRawCitationNlm30CitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitRawCitationNlm30CitationSchemaFilter
 * @ingroup citation_parser_parscit
 *
 * @brief Parsing filter implementation that uses the Parscit web service.
 *
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilter');

define('PARSCIT_WEBSERVICE', 'http://aye.comp.nus.edu.sg/parsCit/parsCit.cgi');

class ParscitRawCitationNlm30CitationSchemaFilter extends Nlm30CitationSchemaFilter {
	/*
	 * Constructor
	 */
	function ParscitRawCitationNlm30CitationSchemaFilter() {
		$this->setDisplayName('ParsCit');

		parent::Nlm30CitationSchemaFilter(NLM30_CITATION_FILTER_PARSE);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.parser.parscit.ParscitRawCitationNlm30CitationSchemaFilter';
	}

	/**
	 * @see Filter::process()
	 * @param $citationString string
	 * @return MetadataDescription
	 */
	function &process($citationString) {
		$nullVar = null;
		$queryParams = array(
			'demo' => '3',
			'textlines' => $citationString
		);

		// Parscit web form - the result is (mal-formed) HTML
		if (is_null($result = $this->callWebService(PARSCIT_WEBSERVICE, $queryParams, XSL_TRANSFORMER_DOCTYPE_STRING, 'POST'))) return $nullVar;

		// Detect errors.
		if (!String::regexp_match('/.*<algorithm[^>]+>.*<\/algorithm>.*/s', $result)) {
			$translationParams = array('filterName' => $this->getDisplayName());
			$this->addError(Locale::translate('submission.citations.filter.webserviceResultTransformationError', $translationParams));
			return $nullVar;
		}

		// Screen-scrape the tagged portion and turn it into XML.
		$xmlResult = String::regexp_replace('/.*<algorithm[^>]+>(.*)<\/algorithm>.*/s', '\1', html_entity_decode($result));
		$xmlResult = String::regexp_replace('/&/', '&amp;', $xmlResult);

		// Transform the result into an array of meta-data.
		if (is_null($metadata = $this->transformWebServiceResults($xmlResult, dirname(__FILE__).DIRECTORY_SEPARATOR.'parscit.xsl'))) return $nullVar;

		// Extract a publisher from the place string if possible.
		$metadata =& $this->fixPublisherNameAndLocation($metadata);

		return $this->getNlm30CitationDescriptionFromMetadataArray($metadata);
	}
}
?>