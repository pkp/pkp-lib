<?php

/**
 * @file classes/citation/IsbndbCitationLookupService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbCitationLookupService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Citation lookup service that uses the ISBNdb web
 *        service to search for book citation metadata.
 */

// $Id$

import('citation.CitationLookupService');

define('CITATION_LOOKUP_ISBNDB_BASEURL', 'http://isbndb.com/api/books.xml?');

class IsbndbCitationLookupService extends CitationLookupService {
	/** @var string ISBNdb API key */
	var $_apiKey = '';
	
	/**
	 * Constructor
	 */
	function IsbndbCitationLookupService() {
		// Meta-data genres that can be processed
		$this->_supportedGenres = array(
			METADATA_GENRE_BOOK
		);
	}
	
	/**
	 * @see CitationLookupService::lookup()
	 * @param $citation Citation
	 * @return Citation a looked up citation
	 */
	function &lookup(&$citation) {
		$authorsString = $citation->getAuthorsString();
		$firstAuthor =& $citation->getFirstAuthor();
		$authorLastName = $firstAuthor->getLastName();
		$title = $citation->getBookTitle();
		$year = substr($citation->getIssuedDate(), 0, 4);
		
		$searchStrings = array(
			// TODO: requires searching index1=isbn
			// $citation->getIsbn();
			$authorsString.' '.$title.' '.$year,
			$authorLastName.' '.$title.' '.$year,
			$authorsString.' '.$title.' c'.$year,
			$authorLastName.' '.$title.' c'.$year,
			$authorsString.' '.$title,
			$authorLastName.' '.$title,
			$title.' '.$year,
			$title.' c'.$year,
			$authorsString.' '.$year,
			$authorLastName.' '.$year,
			$authorsString.' c'.$year,
			$authorLastName.' c'.$year
		);

		// Remove duplicate searches
		$searchStrings = array_unique($searchStrings);

		// ISBNdb search API URL
		$baseUrl = CITATION_LOOKUP_ISBNDB_BASEURL.
		       '&access_key='.urlencode($this->apikey).
		       '&index1=combined&value1=';

		// Create a temporary DOM document
		// FIXME: PHP5 only.
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses
		$resultDOM->recover = true;

		// Run the searches, in order, until we have a result
		foreach ($searchStrings as $searchString) {
			$xmlResult = $this->callWebService($baseUrl.urlencode($searchString));
			$resultDOM->loadXML($xmlResult);

			$numResults = $resultDOM->getElementsByTagName('BookList')->item(0)->getAttribute('total_results');
			if (!empty($numResults)) break;
		}

		$bookData =& $resultDOM->getElementsByTagName('BookData')->item(0);

		// If no book data present, then abort (this includes no search result at all)
		if (empty($bookData)) return null;
		
		$isbn = $bookData->getAttribute('isbn13');
		
		// If we have no ISBN then abort
		if (empty($isbn)) return null;

		// Extract the meta-data for the given ISBN
		$citation = $this->_extract($isbn, $citation);
		return $citation;
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
	 * Fills the given citation object with
	 * meta-data retrieved from ISBNdb
	 * @param $isbn string
	 * @param $citation Citation
	 * @return Citation
	 */
	function &_extract($isbn, &$citation) {
		// Use ISBNdb to get XML metadata for the given ISBN ("results = texts" for extra information)
		$url = CITATION_LOOKUP_ISBNDB_BASEURL.'index1=isbn&results=details,authors'.
		       '&access_key='.urlencode($apikey).
		       '&value1='.$isbn;

		// Create a temporary DOM document
		// FIXME: PHP5 only.
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses
		$resultDOM->recover = true;

		// Run the searches, in order, until we have a result
		$xmlResult = $this->callWebService($baseUrl.urlencode($searchString));
		$metadata = $this->transformWebServiceResults($xmlResult, 'lookup'.DIRECTORY_SEPARATOR.'isbndb.xsl');

		// Extract place and publisher from the combined entry.
		$metadata['place'] = String::regexp_replace('/^(.+):.*/', '\1', $metadata['place-publisher']);
		$metadata['publisher'] = String::regexp_replace('/.*:([^,]+),?.*/', '\1', $metadata['place-publisher']);
		unset($metadata['place-publisher']);
		
		// Reformat the issued date
		$metadata['issuedDate'] = String::regexp_replace('/^[^\d{4}]+(\d{4}).*/', '\1', $metadata['issuedDate']);

		// Clean non-numerics from ISBN
		$metadata['isbn'] = String::regexp_replace('/[^\dX]*/', '', $isbn);

		$citation->setElementsFromArray($metadata);
		return $citation;
	}
}
?>