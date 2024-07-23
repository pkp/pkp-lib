<?php

/**
 * @file classes/xml/XMLParserDOMHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XMLParserDOMHandler
 *
 * @ingroup xml
 *
 * @see PKPXMLParser
 *
 * @brief Default handler for PKPXMLParser returning a simple DOM-style object.
 * This handler parses an XML document into a tree structure of XMLNode objects.
 *
 */

namespace PKP\xml;

use XMLParser;

class XMLParserDOMHandler extends XMLParserHandler
{
    /** @var Root node */
    public ?XMLNode $rootNode;

    /** @var The node currently being parsed */
    public ?XMLNode $currentNode = null;

    /** @var string reference to the current data */
    public ?string $currentData = null;

    /** @var XMLNode[] */
    public array $rootNodes = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Callback function to act as the start element handler.
     */
    public function startElement(XMLParser|PKPXMLParser $parser, string $tag, array $attributes) : void
    {
        $this->currentData = null;
        $node = new XMLNode($tag);
        $node->setAttributes($attributes);

        if (isset($this->currentNode)) {
            $this->currentNode->addChild($node);
            $node->setParent($this->currentNode);
        } else {
            $this->rootNode = & $node;
        }

        $this->currentNode = & $node;
    }

    /**
     * Callback function to act as the end element handler.
     */
    public function endElement(XMLParser|PKPXMLParser $parser, string $tag)
    {
        $this->currentNode->setValue($this->currentData);
        $this->currentNode = & $this->currentNode->getParent();
        $this->currentData = null;
    }

    /**
     * Callback function to act as the character data handler.
     */
    public function characterData(XMLParser|PKPXMLParser $parser, string $data)
    {
        $this->currentData .= $data;
    }

    /**
     * Returns a reference to the root node of the tree representing the document.
     */
    public function getResult() : mixed
    {
        return $this->rootNode;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\xml\XMLParserDOMHandler', '\XMLParserDOMHandler');
}
