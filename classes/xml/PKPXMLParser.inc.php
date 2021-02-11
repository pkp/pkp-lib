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


// The default character encodings
define('XML_PARSER_SOURCE_ENCODING', Config::getVar('i18n', 'client_charset'));
define('XML_PARSER_TARGET_ENCODING', Config::getVar('i18n', 'client_charset'));

import('lib.pkp.classes.xml.XMLParserDOMHandler');

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
	 * @param $file string full path to the XML file
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
	 * @param $filenameOrUrl
	 * @return \GuzzleHttp\Psr7\Stream|null
	 */
	protected function _getStream($filenameOrUrl) {
		if (filter_var($filenameOrUrl, FILTER_VALIDATE_URL)) {
			// Remote URL.
			$client = Application::get()->getHttpClient();
			$response = $client->request('GET', $filenameOrUrl);
			return GuzzleHttp\Psr7\stream_for($response->getBody());
		} elseif (file_exists($filenameOrUrl) && is_readable($filenameOrUrl)) {
			$resource = fopen($filenameOrUrl, 'r');
			return GuzzleHttp\Psr7\stream_for($resource);
		}
		return null;
	}

	/**
	 * Add an error to the current error list
	 * @param $error string
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
	 * @return boolean
	 */
	function getStatus() {
		return empty($this->errors);
	}

	/**
	 * Set the handler to use for parse(...).
	 * @param $handler XMLParserHandler
	 */
	function setHandler($handler) {
		$this->handler = $handler;
	}

	/**
	 * Parse XML data using xml_parse_into_struct and return data in an array.
	 * This is best suited for XML documents with fairly simple structure.
	 * @param $text string XML data
	 * @param $tagsToMatch array optional, if set tags not in the array will be skipped
	 * @return array? a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
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
	 * @param $file string full path to the XML file
	 * @param $tagsToMatch array optional, if set tags not in the array will be skipped
	 * @return array? a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
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
	 * @param $parser resource
	 */
	function destroyParser($parser) {
		xml_parser_free($parser);
	}
}

/**
 * Interface for handler class used by PKPXMLParser.
 * All XML parser handler classes must implement these methods.
 */
class XMLParserHandler {

	/**
	 * Callback function to act as the start element handler.
	 * @param $parser PKPXMLParser
	 * @param $tag string
	 * @param $attributes array
	 */
	function startElement($parser, $tag, $attributes) {
	}

	/**
	 * Callback function to act as the end element handler.
	 * @param $parser PKPXMLParser
	 * @param $tag string
	 */
	function endElement($parser, $tag) {
	}

	/**
	 * Callback function to act as the character data handler.
	 * @param $parser PKPXMLParser
	 * @param $data string
	 */
	function characterData($parser, $data) {
	}

	/**
	 * Returns a resulting data structure representing the parsed content.
	 * The format of this object is specific to the handler.
	 * @return mixed
	 */
	function getResult() {
		return null;
	}

	/**
	 * Perform clean up for this object
	 * @deprecated
	 */
	function destroy() {
	}
}

// For PHP < 8.x, this class used to be called XMLParser. Alias for compatibility when possible.
if (!class_exists('XMLParser')) class_alias('PKPXMLParser', 'XMLParser');
