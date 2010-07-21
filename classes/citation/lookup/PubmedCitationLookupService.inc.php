<?php

/**
 * @file classes/citation/PubmedCitationLookupService.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubmedCitationLookupService
 * @ingroup citation
 * @see CitationMangager
 *
 * @brief Citation lookup service that uses the Pubmed/NCBI eUtils web
 *        services to search for citation metadata.
 */

// $Id$

import('citation.CitationLookupService');

define('CITATION_LOOKUP_PUBMED_BASEURL_ESEARCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed');
define('CITATION_LOOKUP_PUBMED_BASEURL_EFETCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&mode=xml');
define('CITATION_LOOKUP_PUBMED_BASEURL_ELINK', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dbfrom=pubmed&cmd=llinks');

class PubmedCitationLookupService extends CitationLookupService {
	/**
	 * Constructor
	 */
	function PubmedCitationLookupService() {
		// Meta-data genres that can be processed
		$this->_supportedGenres = array(
			METADATA_GENRE_JOURNALARTICLE,
			METADATA_GENRE_PROCEEDING
		);
	}
	
	/**
	 * Try to find a PMID based on the given citation
	 * @see CitationLookupService::lookup()
	 * @param $citation Citation
	 * @return Citation a looked up citation
	 */
	function &lookup(&$citation) {
		// If the citation already has a PMID, skip straight to eFetch; otherwise, try to get one from eSearch
		if (empty($citation->getPmId())) {
			
			// 1) Try a "loose" search based on the author list.
			//    (This works surprisingly well for pubmed.)
			$pmidArrayFromAuthorsSearch =& $this->_doSearch($citation->getAuthorsString());

			// 2) Try a "loose" search based on the article title
			$pmidArrayFromTitleSearch =& $this->_doSearch($citation->getArticleTitle());

			// 3) try a "strict" search based on as much information as possible
			$firstAuthor = $citation->getFirstAuthor();
			$searchTerms = $citation->getArticleTitle().
			               '+AND+'.$firstAuthor->getLastName().'+'.substr($firstAuthor->getFirstName(), 0, 1).'[Auth]'.
			               '+AND+'.$citation->getJournalTitle().'[Jour]';
			
			if (!empty($citation->getIssuedDate()))
				$searchTerms .= '+AND+'.$citation->getIssuedDate().'[DP]';
			if (!empty($citation->getVolume()))
				$searchTerms .= '+AND+'.$citation->getVolume().'[VI]';
			if (!empty($citation->getIssue()))
				$searchTerms .= '+AND+'.$citation->getIssue().'[IP]';
			if (!empty($citation->getFirstPage()))
				$searchTerms .= '+AND+'.$citation->getFirstPage().'[PG]';
				
			$pmidArrayFromStrictSearch =& $this->_doSearch($citation->getArticleTitle());
				
			// TODO: add another search like strict, but without article title
			// e.g.  ...term=Baumgart+Dc[Auth]+AND+Lancet[Jour]+AND+2005[DP]+AND+366[VI]+AND+9492[IP]+AND+1210[PG]

			// Compare the arrays to try to narrow it down to one PMID

			switch (true) {
				// strict search has a single result
				case (count($pmidArrayFromStrictSearch) == 1):
					$pmid = $pmidArrayFromStrictSearch[0];
					break;
					
				// 3-way union
				case (count($intersect = array_intersect($pmidArrayFromTitleSearch, $pmidArrayFromAuthorsSearch, $pmidArrayFromStrictSearch)) == 1):
					$pmid = current($intersect);
					break;

				// 2-way union: title / strict
				case (count($pmid_2way1 = array_intersect($pmidArrayFromTitleSearch, $pmidArrayFromStrictSearch)) == 1):
					$pmid = current($intersect);
					break;
					
				// 2-way union: authors / strict
				case (count($pmid_2way2 = array_intersect($pmidArrayFromAuthorsSearch, $pmidArrayFromStrictSearch)) == 1):
					$pmid = current($intersect);
					break;
					
				// 2-way union: authors / title
				case (count($pmid_2way3 = array_intersect($pmidArrayFromAuthorsSearch, $pmidArrayFromTitleSearch)) == 1):
					$pmid = current($intersect);
					break;
					
				// we only have one result for title
				case (count($pmidArrayFromTitleSearch) == 1):
					$pmid = $pmidArrayFromTitleSearch[0];
					break;

				// we only have one result for authors
				case (count($pmidArrayFromAuthorsSearch) == 1):
					$pmid = $pmidArrayFromAuthorsSearch[0];
					break;

				// we were unable to find a PMID
				default:
					$pmid = '';
			}
		} else {
			$pmid = $metadata['pmid'];
		}

		// If we have a PMID, get a metadata array for it
		if (!empty($pmid)) {
			$citation =& $this->_extract($pmid, $citation);
			return $citation;
		}
		
		// Nothing found
		return null;
	}
	
	//
	// Get/set methods
	//
	
	/**
	 * get the email
	 * @return string
	 */
	function getEmail() {
		return $this->_email;
	}
	
	/**
	 * set the email
	 * @param $email string
	 */
	function setEmail($email) {
		$this->_email = $email;
	}
	
	//
	// Private methods
	//
	/**
	 * Searches the given search terms with the pubmed
	 * eSearch and returns the found PMIDs as an array. 
	 * @param $searchTerms
	 * @return array an array with PMIDs
	 */
	function &_doSearch($searchTerms) {
		assert(!empty($this->getEmail));
		$eSearchUrl = CITATION_LOOKUP_PUBMED_BASEURL_ESEARCH.
				'&tool=PKP-WAL&email='.urlencode($this->getEmail()).'&term='.
				urlencode($searchTerms);
		
		// Call the eSearch web service and get an XML result
		$xmlResult = $this->callWebService($url);
		
		// create a temporary DOM document
		// FIXME: This is PHP5 only. Use DOM XML for PHP4
		$resultDOM = new DOMDocument();
		// Try to handle non-well-formed responses
		$resultDOM->recover = true;

		$resultDOM->loadXML($xmlResult);

		// Loop through any results we have and add them to a PMID array
		$pmidArray = array();
		foreach ($resultDOM->getElementsByTagName('Id') as $idNode) {
			$pmidArray[] = $idNode->textContent;
		}
		
		return $pmidArray; 
	}
	
	/**
	 * Fills the given citation object with
	 * meta-data retrieved from PubMed.
	 * @param $pmid string
	 * @param $citation Citation
	 * @return Citation
	 */
	function &_extract($pmid, &$citation) {

		// Use eFetch to get XML metadata for the given PMID
		$url = CITATION_LOOKUP_PUBMED_BASEURL_EFETCH.
		       '&tool=PKP-WAL&email='.urlencode($email);
		       '&id='.urlencode($pmid);
		
		// Call the eFetch URL and get an XML result
		// TODO: this code should handle an empty return value
		$xmlResult = $this->callWebService($url);
		
		// Create a temporary DOM document
		// FIXME: This is PHP5 only. Use DOM XML for PHP4
		$resultDOM = new DOMDocument();
		$resultDOM->loadXML($xmlResult);

		$citation->setPmId($pmid);
		$citation->setComment('');

		// TODO: The following could be replaced by an XSL mapping
		$citation->setArticleTitle($resultDOM->getElementsByTagName("ArticleTitle")->item(0)->textContent);
		$citation->setJournalTitle($resultDOM->getElementsByTagName("MedlineTA")->item(0)->textContent);
		if ($resultDOM->getElementsByTagName("Volume")->length > 0)
			$citation->setVolume($resultDOM->getElementsByTagName("Volume")->item(0)->textContent);
		if ($resultDOM->getElementsByTagName("Issue")->length > 0)
			$citation->setIssue($resultDOM->getElementsByTagName("Issue")->item(0)->textContent);

		// get list of author full names
		$authors = array();
		foreach ($resultDOM->getElementsByTagName("Author") as $authorNode) {
			$author = new Author();
			$author->setLastName($authorNode->getElementsByTagName("LastName")->item(0)->textContent);

			if ($authorNode->getElementsByTagName("FirstName")->length > 0) {
				$author->setFirstName($authorNode->getElementsByTagName("FirstName")->item(0)->textContent);
			} elseif ($authorNode->getElementsByTagName("ForeName")->length > 0) {
				$author->setFirstName($authorNode->getElementsByTagName("ForeName")->item(0)->textContent);
			}

			// Include collective names
			if ($resultDOM->getElementsByTagName("CollectiveName")->length > 0 && $authorNode->getElementsByTagName("CollectiveName")->item(0)->textContent != '') {
				// FIXME: This corresponds to an NLM-citation <collab> tag and should be part of the Metadata implementation
				// Something like:
				// $author = new CollabAuthor(); 
				// $author->setName($authorNode->getElementsByTagName("CollectiveName")->item(0)->textContent);
			}
			
			$authors[] = $author;
		}
		$citation->setAuthors($authors);

		// Extract pagination
		if (String::regexp_match_get("/^[:p\.\s]*(?P<p1>[Ee]?\d+)(-(?P<p2>\d+))?/", $resultDOM->getElementsByTagName("MedlinePgn")->item(0)->textContent, $pages)) {
			$citation->setFirstPage($pages[1]);
			if (isset($pages[3])) $citation->setLastPage($pages[3]);
		}

		// Get publication date
		// TODO: This could be in multiple places
		if ($resultDOM->getElementsByTagName("ArticleDate")->length > 0) {
			$issuedDate = $resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Year")->item(0)->textContent.
			              '-'.$resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Month")->item(0)->textContent.
			              '-'.$resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Day")->item(0)->textContent;
			$citation->setIssuedDate($issuedDate);
		}

		// Get DOI if it exists
		foreach ($resultDOM->getElementsByTagName("ArticleId") as $idNode) {
			if ($idNode->getAttribute('IdType') == 'doi') $citation->setDoi($idNode->textContent);
		}

		// Use eLink utility to find fulltext links
		$url = CITATION_LOOKUP_PUBMED_BASEURL_ELINK.
		       '&tool=PKP-WAL&email='.urlencode($email).
		       '&id='.$pmid;

		// Call the eLink URL and get an XML result
		$xmlResult = $this->callWebService($url);
		$resultDOM->loadXML($xmlResult);

		// Get a list of possible links
		foreach ($resultDOM->getElementsByTagName("ObjUrl") as $linkOut) {
			$attributes = '';
			foreach ($linkOut->getElementsByTagName("Attribute") as $attribute) $attributes .= strtolower($attribute->textContent).' / ';

			// NB: only add links to open access resources
			if (strpos($attributes, "subscription") === false && strpos($attributes, "membership") === false &&
				 strpos($attributes, "fee") === false && $attributes != "") {
				$links[] = $linkOut->getElementsByTagName("Url")->item(0)->textContent;
			 }
		}

		// Take the first link if we have any left (presumably pubmed returns them in preferential order)
		if (isset($links[0])) $citation->setUrl($links[0]);

		return $citation;
	}
}
?>