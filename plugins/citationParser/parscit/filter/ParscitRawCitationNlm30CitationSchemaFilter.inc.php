<?php

/**
 * @defgroup plugins_citationParser_parscit_filter
 */

/**
 * @file plugins/citationParser/parscit/filter/ParscitRawCitationNlm30CitationSchemaFilter.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParscitRawCitationNlm30CitationSchemaFilter
 * @ingroup plugins_citationParser_parscit_filter
 *
 * @brief Parsing filter implementation that uses the Parscit web service.
 *
 */


import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaFilter');

define('PARSCIT_WEBSERVICE', 'http://aye.comp.nus.edu.sg/parsCit/parsCit.cgi');

class ParscitRawCitationNlm30CitationSchemaFilter extends Nlm30CitationSchemaFilter {
	/*
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function ParscitRawCitationNlm30CitationSchemaFilter(&$filterGroup) {
		$this->setDisplayName('ParsCit');

		parent::Nlm30CitationSchemaFilter($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @see PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.citationParser.parscit.filter.ParscitRawCitationNlm30CitationSchemaFilter';
	}


	//
	// Implement template methods from Filter
	//
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
		$result = html_entity_decode($result);

		// Detect errors.
		if (!String::regexp_match('/.*<algorithm[^>]+>.*<\/algorithm>.*/s', $result)) {
			$translationParams = array('filterName' => $this->getDisplayName());
			$this->addError(__('submission.citations.filter.webserviceResultTransformationError', $translationParams));
			return $nullVar;
		}

		// Screen-scrape the tagged portion and turn it into XML.
		$xmlResult = String::regexp_replace('/.*<algorithm[^>]+>(.*)<\/algorithm>.*/s', '\1', $result);
		$xmlResult = String::regexp_replace('/&/', '&amp;', $xmlResult);

		// Transform the result into an array of meta-data.
		if (is_null($metadata = $this->transformWebServiceResults($xmlResult, dirname(__FILE__).DIRECTORY_SEPARATOR.'parscit.xsl'))) return $nullVar;

		// Extract a publisher from the place string if possible.
		$metadata =& $this->fixPublisherNameAndLocation($metadata);

		return $this->getNlm30CitationDescriptionFromMetadataArray($metadata);
	}
}
?>
