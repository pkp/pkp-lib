<?php

/**
 * @file classes/citation/IsbndbNlmCitationSchemaFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IsbndbNlmCitationSchemaFilter
 * @ingroup citation_lookup_isbndb
 *
 * @brief Abstract filter that wraps the ISBNdb web service.
 */

// $Id$

define('ISBNDB_WEBSERVICE_URL', 'http://isbndb.com/api/books.xml?');

import('metadata.nlm.NlmCitationSchemaFilter');
import('webservice.XmlWebService');

class IsbndbNlmCitationSchemaFilter extends NlmCitationSchemaFilter {
	/** @var string ISBNdb API key */
	var $_apiKey = '';

	/*
	 * Constructor
	 */
	function IsbndbNlmCitationSchemaFilter($apiKey) {
		assert(!empty($apiKey));
		$this->_apiKey = $apiKey;
	}

	//
	// Getters and Setters
	//
	/**
	 * get the apiKey
	 * @return string
	 */
	function getApiKey() {
		return $this->_apiKey;
	}

	//
	// Protected helper methods
	//
	/**
	 * Checks whether the given string is an ISBN
	 * or null.
	 * @param $isbn
	 * @return boolean
	 */
	function isValidIsbn($isbn) {
		// TODO: Validate the ISBN here
		return is_null($isbn) || is_string($output);
	}
}
?>