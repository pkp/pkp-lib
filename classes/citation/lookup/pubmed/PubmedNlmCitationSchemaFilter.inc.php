<?php

/**
 * @defgroup citation_lookup_pubmed
 */

/**
 * @file classes/citation/lookup/pubmed/PubmedNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubmedNlmCitationSchemaFilter
 * @ingroup citation_lookup_pubmed
 *
 * @brief Filter that uses the Pubmed web
 *  service to identify a PMID and corresponding
 *  meta-data for a given NLM citation.
 */

import('lib.pkp.classes.citation.NlmCitationSchemaFilter');
import('lib.pkp.classes.filter.EmailFilterSetting');

define('PUBMED_WEBSERVICE_ESEARCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi');
define('PUBMED_WEBSERVICE_EFETCH', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi');
define('PUBMED_WEBSERVICE_ELINK', 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi');

class PubmedNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/**
	 * Constructor
	 * @param $email string the pubmed registration email
	 */
	function PubmedNlmCitationSchemaFilter($email = null) {
		$this->setDisplayName('PubMed');
		if (!is_null($email)) $this->setData('email', $email);

		// Instantiate the settings of this filter
		$emailSetting = new EmailFilterSetting('email',
				'metadata.filters.pubmed.settings.email.displayName',
				'metadata.filters.pubmed.settings.email.validationMessage',
				FORM_VALIDATOR_OPTIONAL_VALUE);
		$this->addSetting($emailSetting);

		parent::NlmCitationSchemaFilter(
			NLM_CITATION_FILTER_LOOKUP,
			array(
				NLM_PUBLICATION_TYPE_JOURNAL,
				NLM_PUBLICATION_TYPE_CONFPROC
			)
		);
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the email
	 * @return string
	 */
	function getEmail() {
		return $this->getData('email');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.lookup.pubmed.PubmedNlmCitationSchemaFilter';
	}

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
			// Initialize search result arrays.
			$pmidArrayFromAuthorsSearch = $pmidArrayFromTitleSearch = $pmidArrayFromStrictSearch = array();

			// 1) Try a "loose" search based on the author list.
			//    (This works surprisingly well for pubmed.)
			$authors =& $citationDescription->getStatement('person-group[@person-group-type="author"]');
			if (is_array($authors)) {
				import('lib.pkp.classes.metadata.nlm.NlmNameSchemaPersonStringFilter');
				$personNameFilter = new NlmNameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE, '%firstname%%initials%%prefix% %surname%%suffix%', ', ');
				$authorsString = (string)$personNameFilter->execute($authors);
				if (!empty($authorsString)) {
					$pmidArrayFromAuthorsSearch =& $this->_search($authorsString);
				}
			}

			// 2) Try a "loose" search based on the article title
			$articleTitle = (string)$citationDescription->getStatement('article-title');
			if (!empty($articleTitle)) {
				$pmidArrayFromTitleSearch =& $this->_search($articleTitle);
			}

			// 3) Try a "strict" search based on as much information as possible
			$searchProperties = array(
				'article-title' => '',
				'person-group[@person-group-type="author"]' => '[Auth]',
				'source' => '[Jour]',
				'date' => '[DP]',
				'volume' => '[VI]',
				'issue' => '[IP]',
				'fpage' => '[PG]'
			);
			$searchTerms = '';
			$statements = $citationDescription->getStatements();
			foreach($searchProperties as $nlmProperty => $pubmedProperty) {
				if (isset($statements[$nlmProperty])) {
					if (!empty($searchTerms)) $searchTerms .= ' AND ';

					// Special treatment for authors
					if ($nlmProperty == 'person-group[@person-group-type="author"]') {
						assert(isset($statements['person-group[@person-group-type="author"]'][0]));
						$firstAuthor =& $statements['person-group[@person-group-type="author"]'][0];

						// Add surname
						$searchTerms .= (string)$firstAuthor->getStatement('surname');

						// Add initial of the first given name
						$givenNames = $firstAuthor->getStatement('given-names');
						if (is_array($givenNames)) $searchTerms .= ' '.String::substr($givenNames[0], 0, 1);
					} else {
						$searchTerms .= $citationDescription->getStatement($nlmProperty);
					}

					$searchTerms .= $pubmedProperty;
				}
			}

			$pmidArrayFromStrictSearch =& $this->_search($searchTerms);

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
					$pmid = current($pmid_2way1);
					break;

				// 2-way union: authors / strict
				case (count($pmid_2way2 = array_intersect($pmidArrayFromAuthorsSearch, $pmidArrayFromStrictSearch)) == 1):
					$pmid = current($pmid_2way2);
					break;

				// 2-way union: authors / title
				case (count($pmid_2way3 = array_intersect($pmidArrayFromAuthorsSearch, $pmidArrayFromTitleSearch)) == 1):
					$pmid = current($pmid_2way3);
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
			$citationDescription =& $this->_lookup($pmid);
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
	function &_search($searchTerms) {
		$searchParams = array(
			'db' => 'pubmed',
			'tool' => 'pkp-wal',
			'term' => $searchTerms
		);
		if (!is_null($this->getEmail())) $searchParams['email'] = $this->getEmail();

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
	 * @return MetadataDescription
	 */
	function &_lookup($pmid) {
		$nullVar = null;

		// Use eFetch to get XML metadata for the given PMID
		$lookupParams = array(
			'db' => 'pubmed',
			'mode' => 'xml',
			'tool' => 'pkp-wal',
			'id' => $pmid
		);
		if (!is_null($this->getEmail())) $lookupParams['email'] = $this->getEmail();

		// Call the eFetch URL and get an XML result
		if (is_null($resultDOM = $this->callWebService(PUBMED_WEBSERVICE_EFETCH, $lookupParams))) return $nullVar;

		$metadata = array(
			'pub-id[@pub-id-type="pmid"]' => $pmid,
			'article-title' => $resultDOM->getElementsByTagName("ArticleTitle")->item(0)->textContent,
			'source' => $resultDOM->getElementsByTagName("MedlineTA")->item(0)->textContent,
		);

		if ($resultDOM->getElementsByTagName("Volume")->length > 0)
			$metadata['volume'] = $resultDOM->getElementsByTagName("Volume")->item(0)->textContent;
		if ($resultDOM->getElementsByTagName("Issue")->length > 0)
			$metadata['issue'] = $resultDOM->getElementsByTagName("Issue")->item(0)->textContent;

		// Get list of author full names
		foreach ($resultDOM->getElementsByTagName("Author") as $authorNode) {
			if (!isset($metadata['person-group[@person-group-type="author"]']))
				$metadata['person-group[@person-group-type="author"]'] = array();

			// Instantiate an NLM name description
			$authorDescription = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmNameSchema', ASSOC_TYPE_AUTHOR);

			// Surname
			$authorDescription->addStatement('surname', $authorNode->getElementsByTagName("LastName")->item(0)->textContent);

			// Given names
			$givenNamesString = '';
			if ($authorNode->getElementsByTagName("FirstName")->length > 0) {
				$givenNamesString = $authorNode->getElementsByTagName("FirstName")->item(0)->textContent;
			} elseif ($authorNode->getElementsByTagName("ForeName")->length > 0) {
				$givenNamesString = $authorNode->getElementsByTagName("ForeName")->item(0)->textContent;
			}
			if (!empty($givenNamesString)) {
				foreach(explode(' ', $givenNamesString) as $givenName) $authorDescription->addStatement('given-names', String::trimPunctuation($givenName));
			}

			// Suffix
			if ($authorNode->getElementsByTagName("Suffix")->length > 0)
				$authorDescription->addStatement('suffix', $authorNode->getElementsByTagName("Suffix")->item(0)->textContent);

			// Include collective names
			/*if ($resultDOM->getElementsByTagName("CollectiveName")->length > 0 && $authorNode->getElementsByTagName("CollectiveName")->item(0)->textContent != '') {
				// FIXME: This corresponds to an NLM-citation <collab> tag and should be part of the Metadata implementation
			}*/

			$metadata['person-group[@person-group-type="author"]'][] =& $authorDescription;
			unset($authorDescription);
		}

		// Extract pagination
		if (String::regexp_match_get("/^[:p\.\s]*(?P<fpage>[Ee]?\d+)(-(?P<lpage>\d+))?/", $resultDOM->getElementsByTagName("MedlinePgn")->item(0)->textContent, $pages)) {
			$fPage = (integer)$pages['fpage'];
			$metadata['fpage'] = $fPage;
			if (!empty($pages['lpage'])) {
				$lPage = (integer)$pages['lpage'];

				// Deal with shortcuts like '382-7'
				if ($lPage < $fPage) {
					$lPage = (integer)(String::substr($pages['fpage'], 0, -String::strlen($pages['lpage'])).$pages['lpage']);
				}

				$metadata['lpage'] = $lPage;
			}
		}

		// Get publication date (can be in several places in PubMed).
		$dateNode = null;
		if ($resultDOM->getElementsByTagName("ArticleDate")->length > 0) {
			$dateNode = $resultDOM->getElementsByTagName("ArticleDate")->item(0);
		} elseif ($resultDOM->getElementsByTagName("PubDate")->length > 0) {
			$dateNode = $resultDOM->getElementsByTagName("PubDate")->item(0);
		}

		// Retrieve the data parts and assemble date.
		if (!is_null($dateNode)) {
			$publicationDate = '';
			$requiresNormalization = false;
			foreach(array('Year' => 4, 'Month' => 2, 'Day' => 2) as $dateElement => $padding) {
				if ($dateNode->getElementsByTagName($dateElement)->length > 0) {
					if (!empty($publicationDate)) $publicationDate.='-';
					$datePart = str_pad($dateNode->getElementsByTagName($dateElement)->item(0)->textContent, $padding, '0', STR_PAD_LEFT);
					if (!is_numeric($datePart)) $requiresNormalization = true;
					$publicationDate .= $datePart;
				} else {
					break;
				}
			}

			// Normalize the date to NLM standard if necessary.
			if ($requiresNormalization) {
				$dateFilter = new DateStringNormalizerFilter();
				$publicationDate = $dateFilter->execute($publicationDate);
			}

			if (!empty($publicationDate)) $metadata['date'] = $publicationDate;
		}

		// Get publication type
		if ($resultDOM->getElementsByTagName("PublicationType")->length > 0) {
			foreach($resultDOM->getElementsByTagName("PublicationType") as $publicationType) {
				// The vast majority of items on PubMed are articles so catch these...
				if (String::strpos(String::strtolower($publicationType->textContent), 'article') !== false) {
					$metadata['[@publication-type]'] = NLM_PUBLICATION_TYPE_JOURNAL;
					break;
				}
			}
		}

		// Get DOI if it exists
		foreach ($resultDOM->getElementsByTagName("ArticleId") as $idNode) {
			if ($idNode->getAttribute('IdType') == 'doi')
				$metadata['pub-id[@pub-id-type="doi"]'] = $idNode->textContent;
		}

		// Use eLink utility to find fulltext links
		$lookupParams = array(
			'dbfrom' => 'pubmed',
			'cmd' => 'llinks',
			'tool' => 'pkp-wal',
			'id' => $pmid
		);
		if(!is_null($resultDOM = $this->callWebService(PUBMED_WEBSERVICE_ELINK, $lookupParams))) {
			// Get a list of possible links
			foreach ($resultDOM->getElementsByTagName("ObjUrl") as $linkOut) {
				$attributes = '';
				foreach ($linkOut->getElementsByTagName("Attribute") as $attribute) $attributes .= String::strtolower($attribute->textContent).' / ';

				// Only add links to open access resources
				if (String::strpos($attributes, "subscription") === false && String::strpos($attributes, "membership") === false &&
						String::strpos($attributes, "fee") === false && $attributes != "") {
					$links[] = $linkOut->getElementsByTagName("Url")->item(0)->textContent;
				}
			}

			// Take the first link if we have any left (presumably pubmed returns them in preferential order)
			if (isset($links[0])) $metadata['uri'] = $links[0];
		}

		return $this->getNlmCitationDescriptionFromMetadataArray($metadata);
	}
}
?>