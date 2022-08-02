<?php

/**
 * @file classes/db/XMLDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XMLDAO
 * @ingroup db
 *
 * @brief Operations for retrieving and modifying objects from an XML data source.
 */

namespace PKP\db;

use PKP\xml\PKPXMLParser;

class XMLDAO
{
    /**
     * Parse an XML file and return data in an object.
     *
     * @see PKPXMLParser::parse()
     *
     * @param string $file
     */
    public function parse($file)
    {
        $parser = new PKPXMLParser();
        return $parser->parse($file);
    }

    /**
     * Parse an XML file with the specified handler and return data in an object.
     *
     * @see PKPXMLParser::parse()
     *
     * @param string $file
     * @param callable $handler reference to the handler to use with the parser.
     */
    public function parseWithHandler($file, $handler)
    {
        $parser = new PKPXMLParser();
        $parser->setHandler($handler);
        return $parser->parse($file);
    }

    /**
     * Parse an XML file and return data in an array.
     *
     * @see PKPXMLParser::parseStruct()
     *
     * @param string $file
     * @param array $tagsToMatch
     *
     * @return array|null
     */
    public function parseStruct($file, $tagsToMatch = [])
    {
        $parser = new PKPXMLParser();
        return $parser->parseStruct($file, $tagsToMatch);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\db\XMLDAO', '\XMLDAO');
}
