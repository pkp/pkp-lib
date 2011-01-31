<?php

/**
 * @file classes/citation/lookup/isbndb/IsbndbNlmCitationSchemaIsbnFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlmCitationSchemaIsbnFilter
 * @ingroup citation_lookup_isbndb
 *
 * @brief Filter that uses the ISBNdb web
 *  service to identify an ISBN for a given citation.
 */

import('lib.pkp.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaFilter');

class IsbndbNlmCitationSchemaIsbnFilter extends IsbndbNlmCitationSchemaFilter {
	/*
	 * Constructor
	 * @param $apiKey string
	 */
	function IsbndbNlmCitationSchemaIsbnFilter($apiKey = null) {
		$this->setDisplayName('ISBNdb (from NLM)');

		parent::IsbndbNlmCitationSchemaFilter($apiKey);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return array(
			'metadata::lib.pkp.classes.metadata.nlm.NlmCitationSchema(CITATION)',
			'primitive::string'
		);
	}

	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.citation.lookup.isbndb.IsbndbNlmCitationSchemaIsbnFilter';
	}

	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		if (!(is_null($output) || $this->isValidIsbn($output))) return false;
		return parent::supports($input, $output, false, true);
	}

	/**
	 * @see Filter::process()
	 * @param $citationDescription MetadataDescription
	 * @return string an ISBN or null
	 */
	function &process(&$citationDescription) {
		$nullVar = null;

		// Get the search strings
		$searchTemplates =& $this->_getSearchTemplates();
		$searchStrings = $this->constructSearchStrings($searchTemplates, $citationDescription);

		// Run the searches, in order, until we have a result
		$searchParams = array(
			'access_key' => $this->getApiKey(),
			'index1' => 'combined'
		);
		foreach ($searchStrings as $searchString) {
			$searchParams['value1'] = $searchString;
			if (is_null($resultDOM =& $this->callWebService(ISBNDB_WEBSERVICE_URL, $searchParams))) return $nullVar;

			// Did we get a search hit?
			$numResults = '';
			if (is_a($resultDOM->getElementsByTagName('BookList'), 'DOMNodeList')
					&& is_a($resultDOM->getElementsByTagName('BookList')->item(0), 'DOMNode')) {
				$numResults = $resultDOM->getElementsByTagName('BookList')->item(0)->getAttribute('total_results');
			}
			if (!empty($numResults)) break;
		}

		// Retrieve the first search hit
		$bookData = '';
		if (is_a($resultDOM->getElementsByTagName('BookData'), 'DOMNodeList')) {
			$bookData =& $resultDOM->getElementsByTagName('BookData')->item(0);
		}

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
	 * Return an array of search templates.
	 * @return array
	 */
	function &_getSearchTemplates() {
		$searchTemplates = array(
			'%au% %title% %date%',
			'%aulast% %title% %date%',
			'%au% %title% c%date%',
			'%aulast% %title% c%date%',
			'%au% %title%',
			'%aulast% %title%',
			'%title% %date%',
			'%title% c%date%',
			'%au% %date%',
			'%aulast% %date%',
			'%au% c%date%',
			'%aulast% c%date%'
		);
		return $searchTemplates;
	}
}
?>