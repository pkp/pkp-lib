<?php

/**
 * @file classes/citation/PubmedNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubmedNlmCitationSchemaFilter
 * @ingroup citation_lookup_pubmed
 *
 * @brief Filter that uses the Pubmed web
 *  service to identify a PMID and corresponding
 *  meta-data for a given NLM citation.
 */

// $Id$

import('citation.NlmCitationSchemaFilter');

define('PUBMED_WEBSERVICE_ESEARCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed');
define('PUBMED_WEBSERVICE_EFETCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&mode=xml');
define('PUBMED_WEBSERVICE_ELINK', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dbfrom=pubmed&cmd=llinks');

class PubmedNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var unknown_type */
	var $_email;

	/**
	 * Constructor
	 * @param $email string
	 */
	function PubmedNlmCitationSchemaFilter($email) {
		assert(!empty($email));
		$this->_email = $email;

		parent::NlmCitationSchemaFilter(array('journal', 'conf-proc'));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the email
	 * @return string
	 */
	function getEmail() {
		return $this->_email;
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $citationDescription MetadataDescription
	 * @return MetadataDescription
	 */
	function &process(&$citationDescription) {
		$pmid = $citationDescription->getStatement('pub-id[@pub-id-type="pmid"]');

		// If the citation does not have a PMID, try to get one from eSearch
		// otherwise skip directly to eFetch.
		if (empty($pmid)) {

			// 1) Try a "loose" search based on the author list.
			//    (This works surprisingly well for pubmed.)
			$authors =& $citationDescription->getStatement('person-group[@person-group-type="author"]');
			$personNameFilter = new NlmNameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);
			$authorsString = (string)$personNameFilter->execute($authors);
			if (!empty($authorsString)) {
				$pmidArrayFromAuthorsSearch =& $this->_doSearch($authorsString);
			}

			// 2) Try a "loose" search based on the article title
			$articleTitle = (string)$citationDescription->getStatement('article-title');
			if (!empty($articleTitle)) {
				$pmidArrayFromTitleSearch =& $this->_doSearch($articleTitle);
			}

			// 3) try a "strict" search based on as much information as possible
			$searchTerms = $articleTitle;

			$firstAuthorSurname = $firstAuthorGivenName = '';
			if (is_array($authors)) {
				$firstAuthorSurname = $authors[0]->getStatement('surname');
				$givenNames = $authors[0]->getStatement('given-names');
				if (is_array($givenNames)) {
					$firstAuthorGivenName = $givenNames[0];
				}
			}
			if (!empty($firstAuthorSurname)) {
				$searchTerms .= '+AND+'.$firstAuthorSurname;
				if (!empty($firstAuthorGivenName))
					$searchTerms .= '+'.substr($firstAuthorGivenName, 0, 1).'[Auth]';
			}

			if (!empty($citationDescription->getStatement('source')))
				$searchTerms .= '+AND+'.$citationDescription->getStatement('source').'[Jour]';
			if (!empty($citationDescription->getStatement('date')))
				$searchTerms .= '+AND+'.$citationDescription->getStatement('date').'[DP]';
			if (!empty($citationDescription->getStatement('volume')))
				$searchTerms .= '+AND+'.$citationDescription->getStatement('volume').'[VI]';
			if (!empty($citationDescription->getStatement('issue')))
				$searchTerms .= '+AND+'.$citationDescription->getStatement('issue').'[IP]';
			if (!empty($citationDescription->getStatement('fpage')))
				$searchTerms .= '+AND+'.$citationDescription->getStatement('fpage').'[PG]';

			$pmidArrayFromStrictSearch =& $this->_doSearch($searchTerms);

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
		}

		// If we have a PMID, get a metadata array for it
		if (!empty($pmid)) {
			$citationDescription =& $this->_lookup($pmid, $citationDescription);
			return $citationDescription;
		}

		// Nothing found
		$nullVar = null;
		return $nullVar;
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
		$searchParams = array(
			'tool' => 'PKP-WAL',
			'email' => $this->getEmail(),
			'term' => $searchTerms
		);

		// Call the eSearch web service and get an XML result
		if (is_null($resultDOM = $this->callWebService(PUBMED_WEBSERVICE_ESEARCH, $searchParams))) {
			$emptyArray = array();
			return $emptyArray;
		}

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
	 * @param $citationDescription MetadataDescription
	 * @return MetadataDescription
	 */
	function &_lookup($pmid, &$citationDescription) {
		$nullVar = null;

		// Use eFetch to get XML metadata for the given PMID
		$lookupParams = array(
			'tool' => 'PKP-WAL',
			'email' => $this->getEmail(),
			'id' => $pmid
		);

		// Call the eFetch URL and get an XML result
		if (is_null($resultDOM = $this->callWebService(PUBMED_WEBSERVICE_EFETCH, $lookupParams))) return $nullVar;

		$citationDescription->addStatement('pub-id[@pub-id-type="pmid"', $pmid, null, true);

		$citationDescription->addStatement('article-title',
				$resultDOM->getElementsByTagName("ArticleTitle")->item(0)->textContent, null, true);
		$citationDescription->addStatement('source',
				$resultDOM->getElementsByTagName("MedlineTA")->item(0)->textContent, null, true);
		if ($resultDOM->getElementsByTagName("Volume")->length > 0) {
			$citationDescription->addStatement('volume',
					$resultDOM->getElementsByTagName("Volume")->item(0)->textContent, null, true);
		}
		if ($resultDOM->getElementsByTagName("Issue")->length > 0) {
			$citationDescription->addStatement('issue',
					$resultDOM->getElementsByTagName("Issue")->item(0)->textContent, null, true);
		}

		// get list of author full names
		$authorDescriptions = array();
		$nlmNameSchema = new NlmNameSchema();
		foreach ($resultDOM->getElementsByTagName("Author") as $authorNode) {
			$authorDescription = new MetadataDescription($nlmNameSchema, ASSOC_TYPE_AUTHOR);
			$authorDescription->addStatement('surname', $authorNode->getElementsByTagName("LastName")->item(0)->textContent);

			if ($authorNode->getElementsByTagName("FirstName")->length > 0) {
				$authorDescription->addStatement('given-names', $authorNode->getElementsByTagName("FirstName")->item(0)->textContent);
			} elseif ($authorNode->getElementsByTagName("ForeName")->length > 0) {
				$authorDescription->addStatement('given-names', $authorNode->getElementsByTagName("ForeName")->item(0)->textContent);
			}

			// Include collective names
			/*if ($resultDOM->getElementsByTagName("CollectiveName")->length > 0 && $authorNode->getElementsByTagName("CollectiveName")->item(0)->textContent != '') {
				// FIXME: This corresponds to an NLM-citation <collab> tag and should be part of the Metadata implementation
			}*/

			$authorDescriptions[] = $authorDescription;
			unset($authorDescription);
		}
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $authorDescriptions);

		// Extract pagination
		if (String::regexp_match_get("/^[:p\.\s]*(?P<p1>[Ee]?\d+)(-(?P<p2>\d+))?/", $resultDOM->getElementsByTagName("MedlinePgn")->item(0)->textContent, $pages)) {
			$citationDescription->addStatement('fpage', $pages[1]);
			if (isset($pages[3])) $citationDescription->addStatement('lpage', $pages[3]);
		}

		// Get publication date
		// TODO: This could be in multiple places
		if ($resultDOM->getElementsByTagName("ArticleDate")->length > 0) {
			$publicationDate = $resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Year")->item(0)->textContent.
			                   '-'.$resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Month")->item(0)->textContent.
			                   '-'.$resultDOM->getElementsByTagName("ArticleDate")->item(0)->getElementsByTagName("Day")->item(0)->textContent;
			$citationDescription->addStatement('date', $publicationDate);
		}

		// Get DOI if it exists
		foreach ($resultDOM->getElementsByTagName("ArticleId") as $idNode) {
			if ($idNode->getAttribute('IdType') == 'doi')
				$citationDescription->addStatement('pub-id[@pub-id-type="doi"]', $idNode->textContent);
		}

		// Use eLink utility to find fulltext links
		$resultDOM = $this->callWebService(PUBMED_WEBSERVICE_ELINK, $lookupParams);

		// Get a list of possible links
		foreach ($resultDOM->getElementsByTagName("ObjUrl") as $linkOut) {
			$attributes = '';
			foreach ($linkOut->getElementsByTagName("Attribute") as $attribute) $attributes .= String::strtolower($attribute->textContent).' / ';

			// NB: only add links to open access resources
			if (String::strpos($attributes, "subscription") === false && String::strpos($attributes, "membership") === false &&
				 String::strpos($attributes, "fee") === false && $attributes != "") {
				$links[] = $linkOut->getElementsByTagName("Url")->item(0)->textContent;
			 }
		}

		// Take the first link if we have any left (presumably pubmed returns them in preferential order)
		if (isset($links[0])) $citationDescription->addStatement('uri', $links[0]);

		return $citationDescription;
	}
}
?>