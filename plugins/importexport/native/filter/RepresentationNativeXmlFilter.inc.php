<?php

/**
 * @file plugins/importexport/native/filter/RepresentationNativeXmlFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a representation to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class RepresentationNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function RepresentationNativeXmlFilter($filterGroup) {
		$this->setDisplayName('Native XML representation export');
		parent::NativeExportFilter($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.RepresentationNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $representation Representation
	 * @return DOMDocument
	 */
	function &process(&$representation) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
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
	 * @param $doc DOMDocument
	 * @param $representation Representation
	 * @return DOMElement
	 */
	function createRepresentationNode($doc, $representation) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the representation node
		$representationNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRepresentationNodeName());

		// Add metadata
		$this->createLocalizedNodes($doc, $representationNode, 'name', $representation->getName(null));
		$sequenceNode = $doc->createElementNS($deployment->getNamespace(), 'seq');
		$sequenceNode->appendChild($doc->createTextNode($representation->getSeq()));
		$representationNode->appendChild($sequenceNode);

		// Add files
		foreach ($this->getFiles($representation) as $submissionFile) {
			$fileRefNode = $doc->createElementNS($deployment->getNamespace(), 'submission_file_ref');
			$fileRefNode->setAttribute('id', $submissionFile->getFileId());
			$fileRefNode->setAttribute('revision', $submissionFile->getRevision());
			$representationNode->appendChild($fileRefNode);
		}

		return $representationNode;
	}


	//
	// Abstract methods to be implemented by subclasses
	//
	/**
	 * Get the submission files associated with this representation
	 * @param $representation Representation
	 * @return array
	 */
	function getFiles($representation) {
		assert(false); // To be overridden by subclasses
	}
}

?>
