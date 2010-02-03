<?php

/**
 * @file classes/citation/WorldcatNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

// TODO: Might wish to change this if the publication type is 'book', etc. for advanced search
define('WORLDCAT_WEBSERVICE_SEARCH', 'http://www.worldcat.org/search');
define('WORLDCAT_WEBSERVICE_OCLC', 'http://xisbn.worldcat.org/webservices/xid/oclcnum/');
// Lookup in MARCXML which has better granularity than Dublin Core
define('WORLDCAT_WEBSERVICE_EXTRACT', 'http://www.worldcat.org/webservices/catalog/content/');
define('WORLDCAT_WEBSERVICE_XISBN', 'http://xisbn.worldcat.org/webservices/xid/isbn/');

class WorldcatNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var string Worldcat API key */
	var $_apiKey = '';

	/**
	 * Constructor
	 * @param $apiKey string
	 */
	function WorldcatNlmCitationSchemaFilter($apiKey) {
		assert(!empty($apiKey));
		$this->_apiKey = $apiKey;

		parent::NlmCitationSchemaFilter(array('book'));
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
			$result = $this->callWebService(WORLDCAT_WEBSERVICE_SEARCH, $searchParams, XSL_TRANSFORMER_DOCTYPE_STRING);
			if (is_null($result)) continue;

			// parse the OCLC numbers from search results
			String::regexp_match_all('/id="itemid_(\d+)"/', $result, $matches);
			if (!empty($matches[1])) break;
		}

		// If we don't have an OCLC number, then we cannot get any metadata
		if (empty($matches[1])) return $nullVar;

		// use xISBN because it's free
		$isbns = $this->_oclcToIsbns($matches[1][0]);

		if (!empty($this->getApiKey())) {
			// Worldcat lookup only works with an API key
			$citationDescription =& $this->_lookupWorldcat($matches[1][0], $citationDescription);
			if (is_null($citationDescription)) return $citationDescription;

			// Prefer ISBN from xISBN if possible
			if (!empty($isbns[0])) $citationDescription->addStatement('ibsn', $isbns[0], null, true);
			return $citationDescription;
		} elseif (!empty($isbns[0])) {
			// Use the first ISBN if we have multiple
			$citationDescription =& $this->_lookupXIsbn($isbns[0], $citationDescription);
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
		$lookupParams = array(
			'method' => 'getMetadata',
			'format' => 'xml',
			'fl' => '*'
		);
		$resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_OCLC.urlencode($oclcId), $lookupParams);
		if (is_null($resultDOM)) return array();

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
		$lookupParams = array('wskey' => $this->getApiKey());
		$resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_EXTRACT.urlencode($oclcId), $lookupParams);
		if (is_null($resultDOM)) return $resultDOM;

		$metadata = $this->transformWebServiceResults($resultDOM, dirname(__FILE__).DIRECTORY_SEPARATOR.'worldcat.xsl');
		if (is_null($metadata)) return $metadata;
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
		$lookupParams = array(
			'method' => 'getMetadata',
			'format' => 'xml',
			'fl' => '*'
		);
		$resultDOM = $this->callWebService(WORLDCAT_WEBSERVICE_XISBN.urlencode($isbn));
		if (is_null($resultDOM)) return $resultDOM;

		// Extract metadata from response
		$recordNode = $resultDOM->getElementsByTagName('isbn')->item(0);
		if (is_null($recordNode)) return $recordNode;

		$metadata['isbn'] = $isbn;
		$metadata['date'] = $recordNode->getAttribute('year');
		$metadata['edition'] = $recordNode->getAttribute('ed');
		$metadata['source'] = $recordNode->getAttribute('title');
		$metadata['publisher-name'] = $recordNode->getAttribute('publisher');
		$metadata['publisher-loc'] = $recordNode->getAttribute('city');

		// Authors are of low quality in xISBN compared to Worldcat's MARC records
		$personStringFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR);
		$authorDescription =& $personStringFilter->execute($personString);
		$metadata['person-group[@person-group-type="author"]'] = array(&$authorDescription);

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