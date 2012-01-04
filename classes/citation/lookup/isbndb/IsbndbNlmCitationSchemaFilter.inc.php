<?php

/**
 * @defgroup citation_lookup_isbndb
 */

/**
 * @file classes/citation/lookup/isbndb/IsbndbNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlmCitationSchemaFilter
 * @ingroup citation_lookup_isbndb
 *
 * @brief Abstract filter that wraps the ISBNdb web service.
 */

// $Id$

define('ISBNDB_WEBSERVICE_URL', 'http://isbndb.com/api/books.xml');

import('citation.NlmCitationSchemaFilter');

class IsbndbNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var string ISBNdb API key */
	var $_apiKey = '';

	/*
	 * Constructor
	 * @param $apiKey string
	 */
	function IsbndbNlmCitationSchemaFilter($apiKey) {
		assert(!empty($apiKey));
		$this->_apiKey = $apiKey;
		parent::NlmCitationSchemaFilter(array(NLM_PUBLICATION_TYPE_BOOK));
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
	// Protected helper methods
	//
	/**
	 * Checks whether the given string is an ISBN.
	 * @param $isbn
	 * @return boolean
	 */
	function isValidIsbn($isbn) {
		return is_string($isbn) && is_numeric($isbn) && String::strlen($isbn) == 13;
	}
}
?>