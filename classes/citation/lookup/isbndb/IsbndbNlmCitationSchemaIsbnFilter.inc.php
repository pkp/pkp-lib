<?php

/**
 * @file classes/citation/IsbndbNlmCitationSchemaIsbnFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlmCitationSchemaIsbnFilter
 * @ingroup citation_lookup_isbndb
 *
 * @brief Filter that uses the ISBNdb web
 *  service to search for book citation metadata.
 *
 * Input: NLM citation meta-data description
 * Output: matching ISBN number
 */

// $Id$

import('citation.lookup.isbndb.IsbndbNlmCitationSchemaFilter');

class IsbndbNlmCitationSchemaIsbnFilter extends IsbndbNlmCitationSchemaFilter {
	/*
	 * Constructor
	 */
	function IsbndbNlmCitationSchemaIsbnFilter($apiKey) {
		parent::IsbndbNlmCitationSchemaFilter($apiKey);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @return boolean
	 */
	function supports(&$input) {
		// This filter requires PHP5's DOMDocument
		if (!checkPhpVersion('5.0.0')) return false;

		return parent::supports($input);
	}

	/**
	 * @see Filter::isValid()
	 * @param $output mixed
	 * @return boolean
	 */
	function isValid(&$output) {
		return $this->isValidIsbn($output);
	}

	/**
	 * @see Filter::process()
	 * @param $citationDescription MetadataDescription
	 * @return string an ISBN or null
	 */
	function &process(&$citationDescription) {
		$nullVar = null;

		// Get the search strings
		$searchStrings = $this->_constructSearchStrings($citationDescription);

		// Run the searches, in order, until we have a result
		$xmlWebService = new XmlWebService();
		$searchParams = array(
			'access_key' => $this->_apiKey,
			'index1' => 'combined'
		);
		foreach ($searchStrings as $searchString) {
			$searchParams['value1'] = $searchString;
			$webServiceRequest = new WebServiceRequest(ISBNDB_WEBSERVICE_URL, $searchParams);
			$resultDOM = $xmlWebService->call($webServiceRequest);

			// If the web service fails then abort
			if (is_null($resultDOM)) return $nullVar;

			// We need a PHP5 DOMDocument to have access
			// to the getElementsByTagName method
			assert(is_a($resultDOM, 'DOMDocument'));

			// Did we get a search hit?
			$numResults = $resultDOM->getElementsByTagName('BookList')->item(0)->getAttribute('total_results');
			if (!empty($numResults)) break;
		}

		// Retrieve the first search hit
		$bookData =& $resultDOM->getElementsByTagName('BookData')->item(0);

		// If no book data present, then abort (this includes no search result at all)
		if (empty($bookData)) return $nullVar;

		$isbn = $bookData->getAttribute('isbn13');

		// If we have no ISBN then abort
		if (empty($isbn)) return $nullVar;

		return $isbn;
	}

	//
	// Private methods
	//
	/**
	 * Construct an array of search strings from a citation
	 * description.
	 * @param $citationDescription MetadataDescription
	 * @return array
	 */
	function _constructSearchStrings(&$citationDescription) {
		import('metadata.nlm.NlmNameSchemaPersonStringFilter');
		$personStringFilter = new NlmNameSchemaPersonStringFilter();

		// Retrieve the authors
		$authors = $citationDescription->getStatement('person-group[@person-group-type="author"]');
		if (is_array($authors) && count($authors)) {
			$authorLastName = (string)$authors[0]->getStatement('surname');

			// Convert authors' name descriptions to strings
			$authorsStrings = array_map(array($personStringFilter, 'execute'), $authors);
			$authorsString = implode('; ', $authorsStrings);
			$firstAuthor = $authorsStrings[0];
		} else {
			$authorsString = '';
		}

		// Retrieve (default language) title
		$title = $citationDescription->getStatement('source');

		// Extract the year from the publication date
		$year = (string)$citationDescription->getStatement('date');
		$year = (String::strlen($year) > 4 ? String::substr($year, 0, 4) : $year);

		// Construct the search strings
		$searchStrings = array(
			// TODO: requires searching index1=isbn
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

		return $searchStrings;
	}
}
?>