<?php

/**
 * @file plugins/importexport/native/filter/RepresentationNativeXmlFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationNativeXmlFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a representation to a Native XML document
 */

namespace PKP\plugins\importexport\native\filter;

use PKP\filter\FilterGroup;
use PKP\plugins\PluginRegistry;
use PKP\submission\Representation;

class RepresentationNativeXmlFilter extends NativeExportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML representation export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param Representation $representation
     *
     * @return \DOMDocument
     */
    public function &process(&$representation)
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();
        $rootNode = $this->createRepresentationNode($doc, $representation);
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // Representation conversion functions
    //
    /**
     * Create and return a representation node.
     *
     * @param \DOMDocument $doc
     * @param Representation $representation
     *
     * @return \DOMElement
     */
    public function createRepresentationNode($doc, $representation)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the representation node
        $representationNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRepresentationNodeName());

        $representationNode->setAttribute('locale', $representation->getData('locale'));
        if (($urlPath = $representation->getData('urlPath')) !== null) {
	    $representationNode->setAttribute('url_path', $urlPath);
	}

        $this->addIdentifiers($doc, $representationNode, $representation);

        // Add metadata
        $this->createLocalizedNodes($doc, $representationNode, 'name', $representation->getName(null));
        $sequenceNode = $doc->createElementNS($deployment->getNamespace(), 'seq');
        $sequenceNode->appendChild($doc->createTextNode((int) $representation->getSequence()));
        $representationNode->appendChild($sequenceNode);

        $urlRemote = $representation->getData('urlRemote');
        if ($urlRemote) {
            $remoteNode = $doc->createElementNS($deployment->getNamespace(), 'remote');
            $remoteNode->setAttribute('src', $urlRemote);
            $representationNode->appendChild($remoteNode);
        } else {
            // Add files
            foreach ($this->getFiles($representation) as $submissionFile) {
                $fileRefNode = $doc->createElementNS($deployment->getNamespace(), 'submission_file_ref');
                $fileRefNode->setAttribute('id', $submissionFile->getId());
                $representationNode->appendChild($fileRefNode);
            }
        }

        return $representationNode;
    }

    /**
     * Create and add identifier nodes to a representation node.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $representationNode
     * @param Representation $representation
     */
    public function addIdentifiers($doc, $representationNode, $representation)
    {
        $deployment = $this->getDeployment();

        // Add internal ID
        $representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $representation->getId()));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        // Add public ID
        if ($pubId = $representation->getStoredPubId('publisher-id')) {
            $representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
            $node->setAttribute('type', 'public');
            $node->setAttribute('advice', 'update');
        }

        // Add pub IDs by plugin
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
        foreach ($pubIdPlugins as $pubIdPlugin) {
            $this->addPubIdentifier($doc, $representationNode, $representation, $pubIdPlugin->getPubIdType());
        }
        // Also add DOI
        $this->addPubIdentifier($doc, $representationNode, $representation, 'doi');
    }

    /**
     * Add a single pub ID element for a given plugin to the representation.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $representationNode
     * @param Representation $representation
     *
     * @return ?\DOMElement
     */
    public function addPubIdentifier($doc, $representationNode, $representation, $pubIdType)
    {
        $pubId = $representation->getStoredPubId($pubIdType);
        if ($pubId) {
            $deployment = $this->getDeployment();
            $representationNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
            $node->setAttribute('type', $pubIdType);
            $node->setAttribute('advice', 'update');
            return $node;
        }
        return null;
    }

    //
    // Abstract methods to be implemented by subclasses
    //
    /**
     * Get the submission files associated with this representation
     *
     * @param Representation $representation
     *
     * @return array
     */
    public function getFiles($representation)
    {
        assert(false); // To be overridden by subclasses
    }
}
