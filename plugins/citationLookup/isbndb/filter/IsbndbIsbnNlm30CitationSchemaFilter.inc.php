<?php

/**
 * @file plugins/citationLookup/isbndb/filter/IsbndbIsbnNlm30CitationSchemaFilter.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbIsbnNlm30CitationSchemaFilter
 * @ingroup plugins_citationLookup_isbndb_filter
 *
 * @brief Filter that uses the ISBNdb web service to look up
 *  an ISBN and create a NLM citation description from the result.
 */


import('lib.pkp.plugins.citationLookup.isbndb.filter.IsbndbNlm30CitationSchemaFilter');

class IsbndbIsbnNlm30CitationSchemaFilter extends IsbndbNlm30CitationSchemaFilter {
	/*
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function IsbndbIsbnNlm30CitationSchemaFilter(&$filterGroup) {
		$this->setDisplayName('ISBNdb');

		parent::IsbndbNlm30CitationSchemaFilter($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @see PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.citationLookup.isbndb.filter.IsbndbIsbnNlm30CitationSchemaFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		if (!$this->isValidIsbn($input)) return false;
		return parent::supports($input, $output, true);
	}

	/**
	 * @see Filter::process()
	 * @param $isbn string
	 * @return MetadataDescription a looked up citation description
	 *  or null if the filter fails
	 */
	function &process($isbn) {
		$nullVar = null;

		// Instantiate the web service request
		$lookupParams = array(
			'access_key' => $this->getApiKey(),
			'index1' => 'isbn',
			'results' => 'details,authors',
			'value1' => $isbn
		);

		// Call the web service
		if (is_null($resultDOM =& $this->callWebService(ISBNDB_WEBSERVICE_URL, $lookupParams))) return $nullVar;

		// Transform and pre-process the web service result
		if (is_null($metadata =& $this->transformWebServiceResults($resultDOM, dirname(__FILE__).DIRECTORY_SEPARATOR.'isbndb.xsl'))) return $nullVar;

		// Extract place and publisher from the combined entry.
		$metadata['publisher-loc'] = String::trimPunctuation(String::regexp_replace('/^(.+):.*/', '\1', $metadata['place-publisher']));
		$metadata['publisher-name'] = String::trimPunctuation(String::regexp_replace('/.*:([^,]+),?.*/', '\1', $metadata['place-publisher']));
		unset($metadata['place-publisher']);

		// Reformat the publication date
		$metadata['date'] = String::regexp_replace('/^[^\d{4}]+(\d{4}).*/', '\1', $metadata['date']);

		// Clean non-numerics from ISBN
		$metadata['isbn'] = String::regexp_replace('/[^\dX]*/', '', $isbn);

		// Set the publicationType
		$metadata['[@publication-type]'] = NLM30_PUBLICATION_TYPE_BOOK;

		return $this->getNlm30CitationDescriptionFromMetadataArray($metadata);
	}
}
?>
