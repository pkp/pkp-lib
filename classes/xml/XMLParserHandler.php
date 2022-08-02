<?php

/**
 * @file classes/xml/XMLParserHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XMLParserHandler
 * @ingroup xml
 *
 * @brief Interface for handler class used by PKPXMLParser.
 * All XML parser handler classes must implement these methods.
 */

namespace PKP\xml;

class XMLParserHandler {

	/**
	 * Callback function to act as the start element handler.
	 * @param PKPXMLParser $parser
	 * @param string $tag
	 * @param array $attributes
	 */
	function startElement($parser, $tag, $attributes) {
	}

	/**
	 * Callback function to act as the end element handler.
	 * @param PKPXMLParser $parser
	 * @param string $tag
	 */
	function endElement($parser, $tag) {
	}

	/**
	 * Callback function to act as the character data handler.
	 * @param PKPXMLParser $parser
	 * @param string $data
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

if (!PKP_STRICT_MODE) {
	class_alias('\PKP\xml\XMLParserHandler', '\XMLParserHandler');
}
