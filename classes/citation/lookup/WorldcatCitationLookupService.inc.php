<?php

/**
 * @file classes/citation/WorldcatCitationLookupService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorldcatCitationLookupService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Citation lookup service that uses the OCLC Worldcat Search API
 *        and xISBN services to search for book citation metadata.
 */

// $Id$

import('citation.CitationLookupService');

// TODO: Might wish to change this if genre is book, etc. for advanced search
define('CITATION_LOOKUP_WORLDCAT_BASEURL_SEARCH', 'http://www.worldcat.org/search?qt=worldcat_org_all&q=');
define('CITATION_LOOKUP_WORLDCAT_BASEURL_OCLC', 'http://xisbn.worldcat.org/webservices/xid/oclcnum/');
// Extract in MARCXML which has better granularity than Dublin Core
define('CITATION_LOOKUP_WORLDCAT_BASEURL_EXTRACT', 'http://www.worldcat.org/webservices/catalog/content/');
define('CITATION_LOOKUP_WORLDCAT_BASEURL_XISBN', 'http://xisbn.worldcat.org/webservices/xid/isbn/');

class WorldcatCitationLookupService extends CitationLookupService {
	/** @var string worldcat API key */
	var $_apiKey = '';
	
	/**
	 * Constructor
	 */
	function WorldcatCitationLookupService() {
		// Meta-data genres that can be processed
		$this->_supportedGenres = array(
			METADATA_GENRE_JOURNALARTICLE,
			METADATA_GENRE_PROCEEDING,
			METADATA_GENRE_UNKNOWN
		);
	}
	
	/**
	 * Try to find an OCLC number based on the given citation
	 * @see CitationLookupService::lookup()
	 * @param $citation Citation
	 * @return Citation a looked up citation
	 */
	function &lookup(&$citation) {
		$firstAuthor =& $citation->getFirstAuthor();
		$authorLastName = $firstAuthor->getLastName();
		switch ($citation->getGenre()) {
			case METADATA_GENRE_BOOK:
				$title = $citation->getBookTitle();
				break;
				
			default:
				$title = $citation->getArticleTitle();
		}
		$year = substr($citation->getIssuedDate(), 0, 4);
		
		// TODO: This might work better with book search (or not)
		$searchStrings = array();
		if (!empty($citation->getIsbn())) $searchStrings[] = $citation->getIsbn();
		$searchStrings[] = $authorLastName.' '.$title.' '.$year;
		$searchStrings[] = $title.' '.$year;
		$searchStrings[] = $authorLastName.' '.$year;
		$searchStrings[] = $authorLastName.' '.$title;

		// Clean and remove any empty or duplicate searches
		$searchStrings = array_unique($searchStrings);

		// Run the searches, in order, until we have a result
		foreach ($searchStrings as $searchString) {
			// Worldcat Web search; results are (mal-formed) XHTML
			$result = $this->callWebService(CITATION_LOOKUP_WORLDCAT_BASEURL_SEARCH.urlencode($searchString));

			// parse the OCLC numbers from search results
			String::regexp_match_all('/id="itemid_(\d+)"/', $result, $matches);
			if (!empty($matches[1])) break;
		}

		// If we don't have an OCLC number, then we cannot get any metadata
		if (empty($matches[1])) return null;
		
		// use xISBN because it's free
		$isbns = $this->_oclcToIsbns($matches[1][0]);

		if (!empty($this->getApiKey())) {
			// Worldcat extraction only works with an API key
			$citation =& $this->_extractWorldcat($matches[1][0], $citation);

			// Prefer ISBN from xISBN if possible
			if (!empty($isbns[0])) $citation->setIsbn($isbns[0]);
			return $citation;
		} elseif (!empty($isbns[0])) {
			// Use the first ISBN if we have multiple
			$citation =& $this->_extractXIsbn($isbns[0], $citation);
			return $citation;
		}
		
		// Nothing found
		return null;
	}
	
	//
	// Get/set methods
	//
	
	/**
	 * get the apiKey
	 * @return string
	 */
	function getApiKey() {
		return $this->_apiKey;
	}
	
	/**
	 * set the apiKey
	 * @param $apiKey string
	 */
	function setApiKey($apiKey) {
		$this->_apiKey = $apiKey;
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
		$url = CITATION_LOOKUP_WORLDCAT_BASEURL_OCLC.$oclcId.
		       '?method=getMetadata&format=xml&fl=*';
		$xmlResult = $this->callWebService($url);
		
		// Create a temporary DOM document
		// FIXME: PHP5 only 
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses
		$resultDOM->recover = true;
		$resultDOM->loadXML($xmlResult);

		// Extract ISBN from response
		$oclcNode = $resultDOM->getElementsByTagName('oclcnum')->item(0);

		if (isset($oclcNode)) {
			return explode(' ', $oclcNode->getAttribute('isbn'));
		} else {
			return array();
		}
	}
		
	/**
	 * Fills the given citation object with
	 * meta-data retrieved from Worldcat
	 * @param $oclcId string
	 * @param $citation Citation
	 * @return Citation
	 */
	function &_extractWorldcat($oclcId, &$citation) {
		$url = CITATION_LOOKUP_WORLDCAT_BASEURL_EXTRACT.$oclcId.
		       '?wskey='.urlencode($this->getApiKey());
		$xmlResult = $this->callWebService($url);
		$metadata = $this->transformWebServiceResults($xmlResult, 'lookup'.DIRECTORY_SEPARATOR.'worldcat.xsl');

		// Clean data
		// FIXME: Clean MARC author field.
		
		// Clean non-numerics from ISBN
		if (!empty($metadata['isbn'])) $metadata['isbn'] = String::regexp_replace('/[^\dX]*/', '', $metadata['isbn']);

		// Clean non-numerics from issued date (year)
		if (!empty($metadata['issuedDate'])) {
			$metadata['issuedDate'] = String::regexp_replace('/,.*/', ', ', $metadata['issuedDate']);
			$metadata['issuedDate'] = String::regexp_replace('/[^\d{4}]/', '', $metadata['issuedDate']);
		}
		
		$citation->setElementsFromArray($metadata);
		return $citation;
	}

	/**
	 * Fills the given citation object with
	 * meta-data retrieved from xISBN
	 * @param $isbn string
	 * @param $citation Citation
	 * @return Citation
	 */
	function &_extractXIsbn($isbn, &$citation) {
		$url = CITATION_LOOKUP_WORLDCAT_BASEURL_XISBN.$isbn.
		       '?method=getMetadata&format=xml&fl=*';
		$xmlResult = $this->callWebService($url);
		
		// Create a temporary DOM document
		// FIXME: PHP5 only
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses
		$resultDOM->recover = true;
		$resultDOM->loadXML($xmlResult);

		// Extract metadata from response
		$recordNode = $resultDOM->getElementsByTagName('isbn')->item(0);

		$metadata['isbn'] = $isbn;
		$metadata['issuedDate'] = $recordNode->getAttribute('year');
		$metadata['edition'] = $recordNode->getAttribute('ed');
		$metadata['bookTitle'] = $recordNode->getAttribute('title');
		$metadata['publisher'] = $recordNode->getAttribute('publisher');
		$metadata['place'] = $recordNode->getAttribute('city');

		// Authors are of low quality in xISBN compared to the MARC records
		$author = parseAuthorString($recordNode->getAttribute('author'));
		$metadata['authors'] = array($author);

		$citation->setElementsFromArray($metadata);
		return $citation;
	}
}
?>