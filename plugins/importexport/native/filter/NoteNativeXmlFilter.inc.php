<?php

/**
 * @file plugins/importexport/native/filter/NoteNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NoteNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of notes to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class NoteNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML Notes export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NoteNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $notes array Array of Notes
	 * @return DOMDocument
	 */
	function &process(&$notes) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Wrap in a <notes> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'notes');
		foreach ($notes as $note) {
			$rootNode->appendChild($this->createNoteNode($doc, $note));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// Conversion functions
	//
	/**
	 * Create and return a note node.
	 * @param $doc DOMDocument
	 * @param $note Note
	 * @return DOMElement
	 */
	function createNoteNode($doc, $note) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the note node
		$noteNode = $doc->createElementNS($deployment->getNamespace(), 'note');

		$noteNode->setAttribute('assoc_type', $note->getAssocType());
		$noteNode->setAttribute('title', $note->getTitle());
		$noteNode->setAttribute('contents', $note->getContents());

		$authorUser = $note->getUser();
		assert(isset($authorUser));
		$noteNode->setAttribute('author', $authorUser->getUsername());

		if ($dateCreated = $note->getDateCreated()) {
			$noteNode->setAttribute('date_created', strftime('%Y-%m-%d', strtotime($dateCreated)));
		}

		if ($dateModified = $note->getDateModified()) {
			$noteNode->setAttribute('date_modified', strftime('%Y-%m-%d', strtotime($dateModified)));
		}

		$this->addFiles($doc, $noteNode, $note);

		return $noteNode;
	}

	/**
	 * Add the Files for a note to its DOM element.
	 * @param $doc DOMDocument
	 * @param $noteNode DOMElement
	 * @param $note Note
	 */
	function addFiles($doc, $noteNode, $note) {
		$fileDao = DAORegistry::getDAO('SubmissionFileDAO');

		$noteFiles = $fileDao->getAllRevisionsByAssocId(ASSOC_TYPE_NOTE, $note->getId());

		$noteFilesNode = $this->processFiles($noteFiles, $note);
		if ($noteFilesNode->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($noteFilesNode->documentElement, true);
			$noteNode->appendChild($clone);
		}
	}

	/**
	 * Create nodeFiles Node
	 * @param $noteFiles array of SubmissionFiles
	 * @param $note Note
	 * @return DOMDocument
	 */
	function processFiles($noteFiles, $note) {
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'noteFiles');
		foreach ($noteFiles as $noteFile) {
			$rootNode->appendChild($this->createFileNode($doc, $noteFile));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	/**
	 * Create note file node.
	 * @param $doc DOMDocument
	 * @param $file SubmissionFile
	 * @return DOMElement
	 */
	function createFileNode($doc, $file) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the file node
		$fileNode = $doc->createElementNS($deployment->getNamespace(), 'noteFile');

		if ($file) {
			$fileNode->setAttribute('oldFileId', $file->getFileId());
		}

		return $fileNode;
	}
}

?>
