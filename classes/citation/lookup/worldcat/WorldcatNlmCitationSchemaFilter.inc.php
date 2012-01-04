<?php

/**
 * @defgroup citation_lookup_worldcat
 */

/**
 * @file classes/citation/lookup/worldcat/WorldcatNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorldcatNlmCitationSchemaFilter
 * @ingroup citation_lookup_worldcat
 * @see CitationMangager
 *
 * @brief Citation lookup filter that uses the OCLC Worldcat Search API
 *  and xISBN services to search for book citation metadata.
 */

// $Id$

import('citation.NlmCitationSchemaFilter');

// TODO: Might wish to change this if the publication type is NLM_PUBLICATION_TYPE_BOOK, etc. for advanced search
define('WORLDCAT_WEBSERVICE_SEARCH', 'http://www.worldcat.org/search');
define('WORLDCAT_WEBSERVICE_OCLC', 'http://xisbn.worldcat.org/webservices/xid/oclcnum/');
// Lookup in MARCXML which has better granularity than Dublin Core
define('WORLDCAT_WEBSERVICE_EXTRACT', 'http://www.worldcat.org/webservices/catalog/content/');
define('WORLDCAT_WEBSERVICE_XISBN', 'http://xisbn.worldcat.org/webservices/xid/isbn/');
// TODO: Should we use OCLC basic API as fallback (see <http://www.worldcat.org/devnet/wiki/BasicAPIDetails>)?

class WorldcatNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var string Worldcat API key */
	var $_apiKey;

	/**
	 * Constructor
	 * @param $apiKey string
	 */
	function WorldcatNlmCitationSchemaFilter($apiKey = '') {
		$this->_apiKey = $apiKey;

		parent::NlmCitationSchemaFilter(array(NLM_PUBLICATION_TYPE_BOOK));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the apiKey
	 * @return string
	 */
	function getApiKey() {
		return $this->_apiKey;
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $citationDescription MetadataDescription
	 * @return string a DOI or null
	 */
	function &process(&$citationDescription) {
		$nullVar = null;

		// Get the search strings
		$searchTemplates =& $this->_getSearchTemplates();
		$searchStrings = $this->constructSearchStrings($searchTemplates, $citationDescription);

		// Run the searches, in order, until we have a result
		$searchParams = array('qt' => 'worldcat_org_all');
		foreach ($searchStrings as $searchString) {
			$searchParams['q'] = $searchString;
			// Worldcat Web search; results are (mal-formed) XHTML
			if (is_null($result = $this->callWebService(WORLDCAT_WEBSERVICE_SEARCH, $searchParams, XSL_TRANSFORMER_DOCTYPE_STRING))) return $nullVar;

			// parse the OCLC numbers from search results
			String::regexp_match_all('/id="itemid_(\d+)"/', $result, $matches);
			if (!empty($matches[1])) break;
		}

		// If we don't have an OCLC number, then we cannot get any metadata
		if (empty($matches[1])) return $nullVar;

		// use xISBN because it's free
		$isbns = $this->_oclcToIsbns($matches[1][0]);

		$apiKey = $this->getApiKey();
		if (empty($apiKey)) {
			// Use the first ISBN if we have multiple
			$citationDescription =& $this->_lookupXIsbn($isbns[0], $citationDescription);
			return $citationDescription;
		} elseif (!empty($isbns[0])) {
			// Worldcat lookup only works with an API key
			if (is_null($citationDescription =& $this->_lookupWorldcat($matches[1][0], $citationDescription))) return $nullVar;

			// Prefer ISBN from xISBN if possible
			if (!empty($isbns[0])) $citationDescription->addStatement('ibsn', $isbns[0], null, true);
			return $citationDescription;
		}

		// Nothing found
		return $nullVar;
	}

	//
	// Private methods
	//
	/**
	 * Take an OCLC number and return the associated ISBNs as an array
	 * @param $oclcId string
	 * @return array an array of ISBNs or an empty array if none found
	 */
	function _oclcToIsbns($oclcId) {
		$nullVar = null;
		$lookupParams = array(
			'method' => 'getMetadata',
			'format' => 'xml',
			'fl' => '*'
		);
		if (is_null($resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_OCLC.urlencode($oclcId), $lookupParams))) return $nullVar;

		// Extract ISBN from response
		$oclcNode = $resultDOM->getElementsByTagName('oclcnum')->item(0);

		if (isset($oclcNode)) {
			return explode(' ', $oclcNode->getAttribute('isbn'));
		} else {
			return array();
		}
	}

	/**
	 * Fills the given citation description with
	 * meta-data retrieved from Worldcat
	 * @param $oclcId string
	 * @param $citationDescription MetadataDescription
	 * @return MetadataDescription
	 */
	function &_lookupWorldcat($oclcId, &$citationDescription) {
		$nullVar = null;
		$lookupParams = array('wskey' => $this->getApiKey());
		if (is_null($resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_EXTRACT.urlencode($oclcId), $lookupParams))) return $nullVar;

		if (is_null($metadata = $this->transformWebServiceResults($resultDOM, dirname(__FILE__).DIRECTORY_SEPARATOR.'worldcat.xsl'))) return $nullVar;
		// FIXME: Use MARC parsed author field in XSL rather than full name

		// Clean non-numerics from ISBN
		if (!empty($metadata['isbn'])) $metadata['isbn'] = String::regexp_replace('/[^\dX]*/', '', $metadata['isbn']);

		// Clean non-numerics from issued date (year)
		if (!empty($metadata['date'])) {
			$metadata['date'] = String::regexp_replace('/,.*/', ', ', $metadata['date']);
			$metadata['date'] = String::regexp_replace('/[^\d{4}]/', '', $metadata['date']);
		}

		$citationDescription =& $this->addMetadataArrayToNlmCitationDescription($metadata, $citationDescription);
		return $citationDescription;
	}

	/**
	 * Fills the given citation object with
	 * meta-data retrieved from xISBN
	 * @param $isbn string
	 * @param $citationDescription Citation
	 * @return Citation
	 */
	function &_lookupXIsbn($isbn, &$citationDescription) {
		$nullVar = null;
		$lookupParams = array(
			'method' => 'getMetadata',
			'format' => 'xml',
			'fl' => '*'
		);
		if (is_null($resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_XISBN.urlencode($isbn), $lookupParams))) return $nullVar;

		// Extract metadata from response
		if (is_null($recordNode = $resultDOM->getElementsByTagName('isbn')->item(0))) return $nullVar;

		$metadata['isbn'] = $isbn;
		$metadata['date'] = $recordNode->getAttribute('year');
		$metadata['edition'] = $recordNode->getAttribute('ed');
		$metadata['source'] = $recordNode->getAttribute('title');
		$metadata['publisher-name'] = $recordNode->getAttribute('publisher');
		$metadata['publisher-loc'] = $recordNode->getAttribute('city');
		// Authors are of low quality in xISBN compared to Worldcat's MARC records
		$metadata['author'] = $recordNode->getAttribute('author');

		// Clean and process the meta-data
		$metadata =& $this->postProcessMetadataArray($metadata);
		$citationDescription =& $this->addMetadataArrayToNlmCitationDescription($metadata, $citationDescription);
		return $citationDescription;
	}

	//
	// Private methods
	//
	/**
	 * Return an array of search templates.
	 * @return array
	 */
	function &_getSearchTemplates() {
		$searchTemplates = array(
			'%isbn%',
			'%aulast% %title% %date%',
			'%title% %date%',
			'%aulast% %date%',
			'%aulast% %title%',
		);
		return $searchTemplates;
	}
}
?>