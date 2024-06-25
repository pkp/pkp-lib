<?php

/**
 * @file classes/xml/XMLParserHandler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XMLParserHandler
 *
 * @brief Interface for handler class used by PKPXMLParser.
 * All XML parser handler classes must implement these methods.
 */

namespace PKP\xml;

abstract class XMLParserHandler
{
    /**
     * Callback function to act as the start element handler.
     */
    public function startElement(PKPXMLParser $parser, string $tag, array $attributes)
    {
    }

    /**
     * Callback function to act as the end element handler.
     */
    public function endElement(PKPXMLParser $parser, string $tag)
    {
    }

    /**
     * Callback function to act as the character data handler.
     */
    public function characterData(PKPXMLParser $parser, string $data)
    {
    }

    /**
     * Returns a resulting data structure representing the parsed content.
     * The format of this object is specific to the handler.
     */
    abstract public function getResult() : mixed;
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\xml\XMLParserHandler', '\XMLParserHandler');
}
