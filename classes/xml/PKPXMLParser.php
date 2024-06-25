<?php

/**
 * @defgroup xml XML
 * Implements XML parsing and creation concerns.
 */

/**
 * @file classes/xml/PKPXMLParser.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPXMLParser
 *
 * @ingroup xml
 *
 * @brief Generic class for parsing an XML document into a data structure.
 */

namespace PKP\xml;

use PKP\file\FileManager;
use XMLParser;

class PKPXMLParser
{
    public const XML_PARSER_SOURCE_ENCODING = 'utf-8';

    public const XML_PARSER_TARGET_ENCODING = 'utf-8';

    /** @var XMLParserHandler instance of XMLParserHandler */
    public $handler;

    /** @var array List of error strings */
    public $errors;

    /**
     * Constructor.
     * Initialize parser and set parser options.
     */
    public function __construct()
    {
        $this->errors = [];
    }

    public function parseText($text)
    {
        $parser = $this->createParser();
        $handler = null;
        if (!isset($this->handler)) {
            // Use default handler for parsing
            $handler = new XMLParserDOMHandler();
            $this->setHandler($handler);
        }

        xml_set_object($parser, $this->handler);
        xml_set_element_handler($parser, 'startElement', 'endElement');
        xml_set_character_data_handler($parser, 'characterData');

        if (!xml_parse($parser, $text, true)) {
            $this->addError(xml_error_string(xml_get_error_code($parser)));
        }

        $result = $this->handler->getResult();
        $this->destroyParser($parser);
        return $result;
    }

    /**
     * Parse an XML file using the specified handler.
     * If no handler has been specified, XMLParserDOMHandler is used by default, returning a tree structure representing the document.
     *
     * @param string $file full path to the XML file
     *
     * @return ?object|false actual return type depends on the handler
     */
    public function parse($file)
    {
        $parser = $this->createParser();
        $handler = null;
        if (!isset($this->handler)) {
            // Use default handler for parsing
            $handler = new XMLParserDOMHandler();
            $this->setHandler($handler);
        }

        xml_set_object($parser, $this->handler);
        xml_set_element_handler($parser, 'startElement', 'endElement');
        xml_set_character_data_handler($parser, 'characterData');

        if (!$stream = FileManager::getStream($file)) {
            return false;
        }

        while (($data = $stream->read(16384)) !== '') {
            if (!xml_parse($parser, $data, $stream->eof())) {
                $this->addError(xml_error_string(xml_get_error_code($parser)));
            }
        }

        $stream->close();
        $result = $this->handler->getResult();
        $this->destroyParser($parser);
        return $result;
    }

    /**
     * Add an error to the current error list
     *
     * @param string $error
     */
    public function addError($error)
    {
        array_push($this->errors, $error);
    }

    /**
     * Get the current list of errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Determine whether or not the parser encountered an error (false)
     * or completed successfully (true)
     *
     * @return bool
     */
    public function getStatus()
    {
        return empty($this->errors);
    }

    /**
     * Set the handler to use for parse(...).
     *
     * @param XMLParserHandler $handler
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }

    /**
     * Parse XML data using xml_parse_into_struct and return data in an array.
     * This is best suited for XML documents with fairly simple structure.
     *
     * @param string $text XML data
     * @param array $tagsToMatch optional, if set tags not in the array will be skipped
     *
     * @return array|null a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
     */
    public function parseTextStruct($text, $tagsToMatch = [])
    {
        $parser = $this->createParser();
        $result = xml_parse_into_struct($parser, $text, $values, $tags);
        $this->destroyParser($parser);
        if (!$result) {
            return null;
        }

        // Clean up data struct, removing undesired tags if necessary
        $data = [];
        foreach ($tags as $key => $indices) {
            if (!empty($tagsToMatch) && !in_array($key, $tagsToMatch)) {
                continue;
            }

            $data[$key] = [];

            foreach ($indices as $index) {
                if (!isset($values[$index]['type']) || ($values[$index]['type'] != 'open' && $values[$index]['type'] != 'complete')) {
                    continue;
                }

                $data[$key][] = [
                    'attributes' => $values[$index]['attributes'] ?? [],
                    'value' => $values[$index]['value'] ?? ''
                ];
            }
        }

        return $data;
    }

    /**
     * Parse an XML file using xml_parse_into_struct and return data in an array.
     * This is best suited for XML documents with fairly simple structure.
     *
     * @param string $file full path to the XML file
     * @param array $tagsToMatch optional, if set tags not in the array will be skipped
     *
     * @return bool|array|null a struct of the form ($TAG => array('attributes' => array( ... ), 'value' => $VALUE), ... )
     */
    public function parseStruct($file, $tagsToMatch = [])
    {
        $stream = FileManager::getStream($file);
        $fileContents = $stream->getContents();
        if (!$fileContents) {
            return false;
        }
        return $this->parseTextStruct($fileContents, $tagsToMatch);
    }

    /**
     * Initialize a new XML parser.
     *
     * @return XMLParser
     */
    public function createParser()
    {
        $parser = xml_parser_create(static::XML_PARSER_SOURCE_ENCODING);
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, static::XML_PARSER_TARGET_ENCODING);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        return $parser;
    }

    /**
     * Destroy XML parser.
     *
     * @param XMLParser $parser
     */
    public function destroyParser($parser)
    {
        xml_parser_free($parser);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\xml\PKPXMLParser', '\PKPXMLParser');
}
