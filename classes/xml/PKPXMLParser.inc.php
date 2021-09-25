<?php

/**
 * @defgroup xml XML
 * Implements XML parsing and creation concerns.
 */

/**
 * @file classes/xml/PKPXMLParser.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPXMLParser
 * @ingroup xml
 *
 * @brief Generic class for parsing an XML document into a data structure.
 */

namespace PKP\xml;

use PKP\facades\Locale;

use APP\core\Application;

// The default character encodings
define('XML_PARSER_SOURCE_ENCODING', Locale::getDefaultEncoding());
define('XML_PARSER_TARGET_ENCODING', Locale::getDefaultEncoding());

class PKPXMLParser {
	/** @var object instance of XMLParserHandler */
	var $handler;

	/** @var array List of error strings */
	var $errors;

	/**
	 * Constructor.
	 * Initialize parser and set parser options.
	 */
	function __construct() {
		$this->errors = array();
	}

	function parseText($text) {
		$parser = $this->createParser();

		if (!isset($this->handler)) {
			// Use default handler for parsing
			$handler = new PKPXMLParserDOMHandler();
			$this->setHandler($handler);
		}

		xml_set_object($parser, $this->handler);
		xml_set_element_handler($parser, "startElement", "endElement");
		xml_set_character_data_handler($parser, "characterData");

		if (!xml_parse($parser, $text, true)) {
			$this->addError(xml_error_string(xml_get_error_code($parser)));
		}

		$result = $this->handler->getResult();
		$this->destroyParser($parser);
		if (isset($handler)) {
			$handler->destroy();
		}
		return $result;
	}

	/**
	 * Parse an XML file using the specified handler.
	 * If no handler has been specified, XMLParserDOMHandler is used by default, returning a tree structure representing the document.
	 * @param string $file full path to the XML file
	 * @return object|false actual return type depends on the handler
	 */
	function parse($file) {
		$parser = $this->createParser();

		if (!isset($this->handler)) {
			// Use default handler for parsing
			$handler = new XMLParserDOMHandler();
			$this->setHandler($handler);
		}

		xml_set_object($parser, $this->handler);
		xml_set_element_handler($parser, "startElement", "endElement");
		xml_set_character_data_handler($parser, "characterData");

		if (!$stream = $this->_getStream($file)) return false;

		while (($data = $stream->read(16384)) !== '') {
			if (!xml_parse($parser, $data, $stream->eof())) {
				$this->addError(xml_error_string(xml_get_error_code($parser)));
			}
		}

		$stream->close();
		$result = $this->handler->getResult();
		$this->destroyParser($parser);
		if (isset($handler)) {
			$handler->destroy();
		}
		return $result;
	}

	/**
	 * Get a PSR7 stream given a filename or URL.
	 * @param string $filenameOrUrl
	 * @return \GuzzleHttp\Psr7\Stream|null
	 */
	protected function _getStream($filenameOrUrl) {
		if (filter_var($filenameOrUrl, FILTER_VALIDATE_URL)) {
			// Remote URL.
			$client = Application::get()->getHttpClient();
			$response = $client->request('GET', $filenameOrUrl);
			return \GuzzleHttp\Psr7\stream_for($response->getBody());
		} elseif (file_exists($filenameOrUrl) && is_readable($filenameOrUrl)) {
			$resource = fopen($filenameOrUrl, 'r');
			return \GuzzleHttp\Psr7\stream_for($resource);
		}
		return null;
	}

	/**
	 * Add an error to the current error list
	 * @param string $error
	 */
	function addError($error) {
		array_push($this->errors, $error);
	}

	/**
	 * Get the current list of errors
	 */
	function getErrors() {
		return $this->errors;
	}

	/**
	 * Determine whether or not the parser encountered an error (false)
	 * or completed successfully (true)
	 * @return bool
	 */
	function getStatus() {
		return empty($this->errors);
	}

	/**
	 * Set the handler to use for parse(...).
	 * @param XMLParserHandler $handler
	 */
	function setHandler($handler) {
		$this->handler = $handler;
	}

	/**
	 * Parse XML data using xml_parse_into_struct and return data in an array.
	 * This is best suited for XML documents with fairly simple structure.
	 * @param string $text XML data
	 * @param array $tagsToMatch optional, if set tags not in the array will be skipped
	 * @return array|null a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
	 */
	function parseTextStruct($text, $tagsToMatch = array()) {
		$parser = $this->createParser();
		$result = xml_parse_into_struct($parser, $text, $values, $tags);
		$this->destroyParser($parser);
		if (!$result) return null;

		// Clean up data struct, removing undesired tags if necessary
		$data = array();
		foreach ($tags as $key => $indices) {
			if (!empty($tagsToMatch) && !in_array($key, $tagsToMatch)) {
				continue;
			}

			$data[$key] = array();

			foreach ($indices as $index) {
				if (!isset($values[$index]['type']) || ($values[$index]['type'] != 'open' && $values[$index]['type'] != 'complete')) {
					continue;
				}

				$data[$key][] = array(
					'attributes' => isset($values[$index]['attributes']) ? $values[$index]['attributes'] : array(),
					'value' => isset($values[$index]['value']) ? $values[$index]['value'] : ''
				);
			}
		}

		return $data;
	}

	/**
	 * Parse an XML file using xml_parse_into_struct and return data in an array.
	 * This is best suited for XML documents with fairly simple structure.
	 * @param string $file full path to the XML file
	 * @param array $tagsToMatch optional, if set tags not in the array will be skipped
	 * @return array|null a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
	 */
	function parseStruct($file, $tagsToMatch = array()) {
		$stream = $this->_getStream($file);
		$fileContents = $stream->getContents();
		if (!$fileContents) {
			return false;
		}
		return $this->parseTextStruct($fileContents, $tagsToMatch);
	}

	/**
	 * Initialize a new XML parser.
	 * @return resource
	 */
	function createParser() {
		$parser = xml_parser_create(XML_PARSER_SOURCE_ENCODING);
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, XML_PARSER_TARGET_ENCODING);
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
		return $parser;
	}

	/**
	 * Destroy XML parser.
	 * @param resource $parser
	 */
	function destroyParser($parser) {
		xml_parser_free($parser);
	}
}

if (!PKP_STRICT_MODE) {
	class_alias('\PKP\xml\PKPXMLParser', '\PKPXMLParser');

	// For PHP < 8.x, this class used to be called XMLParser. Alias for compatibility when possible.
	if (!class_exists('XMLParser')) class_alias('\PKPXMLParser', '\XMLParser');
}
