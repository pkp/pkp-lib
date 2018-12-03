<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlNoteFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlNoteFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of notes
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlNoteFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML notes import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'notes';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'note';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlNoteFilter';
	}


	/**
	 * Handle a note element
	 * @param $node DOMElement
	 * @return array Array of note objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		$queryId = $deployment->getProcessedObjectsIds(ASSOC_TYPE_QUERY);

		// Create the data object
		$noteDao = DAORegistry::getDAO('NoteDAO');

		/** @var $note Note */
		$note = $noteDao->newDataObject();
		$note->setAssocType(ASSOC_TYPE_QUERY);
		$note->setAssocId($queryId[0]);
		$note->setTitle($node->getAttribute('title'));
		$note->setContents($node->getAttribute('contents'));

		if ($dateCreated = $node->getAttribute('date_created')){
			$note->setDateCreated(strtotime($dateCreated));
		}

		if ($dateModified = $node->getAttribute('date_modified')){
			$note->setDateModified(strtotime($dateModified));
		}

		$username = $node->getAttribute('author');
		if (!$username) {
			$user = $deployment->getUser();
		} else {
			// Determine the user based on the username
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getByUsername($username);
		}
		if ($user) {
			$queryDao = DAORegistry::getDAO('QueryDAO');

			$note->setUserId($user->getId());
		} else {
			$deployment->addError(ASSOC_TYPE_QUERY, $query->getId(), __('plugins.importexport.common.error.unknownQueryParticipant', array('param' => $username)));
			$errorOccured = true;
		}

		if ($errorOccured) {
			// if error occured, the file cannot be inserted into DB
			$note = null;
		} else {
			$noteDao->insertObject($note);
		}

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'noteFiles':
						$this->parseNoteFiles($n, $note);
						break;
				}
			}
		}

		return $note;
	}

	/**
	 * Parse a note file element
	 * @param $node DOMElement
	 * @param $note Note
	 */
	function parseNoteFiles($node, $note) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'noteFile');
				$this->parseNoteFile($n, $note);
			}
		}
	}

	/**
	 * Parse a note file
	 * @param $n DOMElement
	 * @param $note Note
	 */
	function parseNoteFile($n, $note) {
		$deployment = $this->getDeployment();

		$oldFileId = $n->getAttribute('oldFileId');
		$revision = $n->getAttribute('revision');

		$newFileId = $deployment->getFileDBId($oldFileId);

		$fileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$fileDao->assignAssocTypeAndIdToFile($newFileId, ASSOC_TYPE_NOTE, $note->getId(), $revision);
	}
}

?>
