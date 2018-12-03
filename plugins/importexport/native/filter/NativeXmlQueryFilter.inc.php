<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlQueryFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlQueryFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of Queries
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlQueryFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML Query import');
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
		return 'queries';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'query';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlQueryFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of Query objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		// Create the data object
		$queryDao = DAORegistry::getDAO('QueryDAO');

		/** @var $query Query */
		$query = $queryDao->newDataObject();
		$query->setAssocType(ASSOC_TYPE_SUBMISSION);
		$query->setAssocId($submission->getId());
		$query->setStageId($node->getAttribute('stage_id'));
		$query->setSequence($node->getAttribute('seq'));
		$query->setIsClosed($node->getAttribute('closed'));

		$queryInsertedId = $queryDao->insertObject($query);
		$deployment->addProcessedObjectId(ASSOC_TYPE_QUERY, $queryInsertedId);

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'participants':
						$this->parseParticipants($n, $queryInsertedId);
						break;
					case 'notes':
						$this->parseNotes($n, $queryInsertedId);
						break;
				}
			}
		}

		return $query;
	}

	/**
	 * Parse a notes element
	 * @param $node DOMElement
	 * @param $query int
	 */
	function parseNotes($node, $queryId) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'query');
				$this->parseNote($n, $queryId);
			}
		}
	}

	/**
	 * Parse a note.
	 * @param $n DOMElement
	 * @param $queryId int
	 */
	function parseNote($n, $queryId) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$importFilters = $filterDao->getObjectsByGroup('native-xml=>note');
		assert(count($importFilters)==1); // Assert only a single unserialization filter
		$importFilter = array_shift($importFilters);
		$importFilter->setDeployment($this->getDeployment());
		$noteDoc = new DOMDocument();
		$noteDoc->appendChild($noteDoc->importNode($n, true));
		return $importFilter->execute($noteDoc);
	}

	/**
	 * Parse participants element
	 * @param $node DOMElement
	 * @param $queryId int
	 */
	function parseParticipants($node, $queryId) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'participant');
				$this->parseParticipant($n, $queryId);
			}
		}
	}

	/**
	 * Parse a participant
	 * @param $n DOMElement
	 * @param $queryId int
	 */
	function parseParticipant($n, $queryId) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		$username = $n->getAttribute('username');
		if (!$username) {
			$user = $deployment->getUser();
		} else {
			// Determine the user based on the username
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getByUsername($username);
		}
		if ($user) {
			$queryDao = DAORegistry::getDAO('QueryDAO');

			$queryDao->insertParticipant($queryId, $user->getId());
		} else {
			$deployment->addError(ASSOC_TYPE_QUERY, $query->getId(), __('plugins.importexport.common.error.unknownQueryParticipant', array('param' => $username)));
		}
	}
}

?>
