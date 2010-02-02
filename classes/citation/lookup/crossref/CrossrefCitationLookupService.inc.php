<?php

/**
 * @file classes/citation/CrossrefCitationLookupService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CrossrefCitationLookupService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Citation lookup service that uses the CrossRef OpenURL interface
 *        to search for journal article citation metadata.
 */

// $Id$

import('citation.CitationLookupService');

define('CITATION_LOOKUP_CROSSREF_BASEURL', 'http://www.crossref.org/openurl/');

class CrossrefCitationLookupService extends CitationLookupService {
	/** @var string CrossReff registered access email */
	var $_email = '';
	
	/**
	 * Constructor
	 */
	function CrossrefCitationLookupService() {
		// Meta-data genres that can be processed
		$this->_supportedGenres = array(
			METADATA_GENRE_JOURNALARTICLE,
			// METADATA_GENRE_CONFERENCEPROCEEDING, FIXME: not yet implemented in XSL
			METADATA_GENRE_BOOK
		);
	}
	
	//
	// Get/set methods
	//
	
	/**
	 * get the access email
	 * @return string
	 */
	function getEmail() {
		return $this->_email;
	}
	
	/**
	 * set the access email
	 * @param $email string
	 */
	function setEmail($email) {
		$this->_email = $email;
	}
	
	/**
	 * @see CitationLookupService::lookup()
	 * @param $citation Citation
	 * @return Citation a looked up citation
	 */
	function &lookup(&$citation) {
		// Make sure we've got an email set, otherwise we
		// cannot use this lookup service.
		$email = $this->getEmail();
		assert(isset($email));
		
		$doi = $citation->getDOI();
		if (!empty($doi)) {
			// If we have a DOI, use that directly
			$query = '?pid='.urlencode($email).'&redirect=false&format=unixref&id=doi:'.
			         urlencode($citation->getDOI());
		} else {
			// Use the CrossRef OpenURL resolver
			// FIXME: the url must depend on the meta-data schema (i.e. journal, book, dissertation)
			$query = '?pid='.urlencode($email).'&url_ver=Z39.88-2004'.
			         '&redirect=false&format=unixref&rft_val_fmt=info:ofi/fmt:kev:mtx:journal'.
			         $this->openUrlKevEncode($citation);
			
			// TODO: Possibly implement a looping search like for other lookup services.
		}
		
		// Extract the meta-data for the given query
		$citation = $this->_extract($query, $citation);
		if (!is_null($citation)) return $citation;
		
		$nullVar = null;
		return $nullVar;
	}
	
	//
	// Private methods
	//
	/**
	 * Take a CrossRef query string, call the web-service
	 * and fill the given citation object with the results.
	 * @param $query string
	 * @param $citation Citation
	 * @return Citation
	 */
	function &_extract($query, &$citation) {
		// Call the CrossRef web service 
		$resultXml = $this->callWebService(CITATION_LOOKUP_CROSSREF_BASEURL.$query);
		
		// Check whether a result was returned.
		if (is_null($resultXml)) {
			$nullVar = null;
			return $nullVar;
		}
		
		// Remove default namespaces from XML as CrossRef doesn't
		// set them reliably and element names are unique anyway.
		$resultXml = String::regexp_replace('/ xmlns="[^"]+"/', '', $resultXml);

		$metadata = $this->transformWebServiceResults($resultXml, 'lookup'.DIRECTORY_SEPARATOR.'crossref.xsl');
		
		$citation->setElementsFromArray($metadata);
		return $citation;
	}
	
	/**
	 * Takes a Citation object and returns it's contents as a
	 * KEV-encoded OpenURL 1.0 string.
	 * 
	 * @param $citation Citation
	 * @return string KEV encoded OpenURL meta-data
	 */
	function openUrlKevEncode(&$citation) {
		// TODO: Include all OpenURL elements
		$elementMapping = array(
			'bookTitle' => 'btitle',
			'journalTitle' => 'jtitle',
			'articleTitle' => 'atitle',
			'issn' => 'issn',
			'artNum' => 'artnum',
			'issuedDate' => 'date',
			'volume' => 'volume',
			'issue' => 'issue',
			'firstPage' => 'spage',
			'lastPage' => 'epage',
		);
		
		$metadataSource = $citation->getNonEmptyElementsAsArray();

		// build an array of openurl elements
		$metadataTarget = array();
		foreach ($metadataSource as $elementName => $elementValue) {
			if (isset($elementMapping[$elementName])) {
				$metadataTarget[$elementMapping[$elementName]] = $elementValue;
			}
		}
		
		// Set the author from the author object
		$author = $citation->getFirstAuthor();
		if (!is_null($author)) {
			$metadataTarget['aufirst'] = $author->getFirstName();
			$metadataTarget['aulast'] = $author->getLastName();
		}

		// Build the OpenURL KEV encoded string
		$openUrl = '';
		foreach ($metadataTarget as $elementName => $elementValue) {
			$openUrl .= '&rft.'.$elementName.'='.urlencode($elementValue);
		}
		
		return $openUrl;
	}
}
?>