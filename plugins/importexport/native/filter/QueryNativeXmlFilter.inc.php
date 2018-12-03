<?php

/**
 * @file plugins/importexport/native/filter/QueryNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of Queries to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class QueryNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML Queries export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.QueryNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $reviewRounds array Array of Queries
	 * @return DOMDocument
	 */
	function &process(&$queries) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Wrap in a <queries> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'queries');
		foreach ($queries as $query) {
			$rootNode->appendChild($this->createQueryNode($doc, $query));
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
	 * Create and return a Query node.
	 * @param $doc DOMDocument
	 * @param $query Query
	 * @return DOMElement
	 */
	function createQueryNode($doc, $query) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the query node
		$queryNode = $doc->createElementNS($deployment->getNamespace(), 'query');

		$queryNode->setAttribute('assoc_type', $query->getAssocType());
		$queryNode->setAttribute('stage_id', $query->getStageId());
		$queryNode->setAttribute('seq', $query->getSequence());
		$queryNode->setAttribute('closed', $query->getIsClosed());

		$this->addNotes($doc, $queryNode, $query);
		$this->addQueryParticipants($doc, $queryNode, $query);

		return $queryNode;
	}

	/**
	 * Add the Notes for a query to its DOM element.
	 * @param $doc DOMDocument
	 * @param $queryNode DOMElement
	 * @param $query Query
	 */
	function addNotes($doc, $queryNode, $query) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$nativeExportFilters = $filterDao->getObjectsByGroup('note=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		$notesDao = DAORegistry::getDAO('NoteDAO');
		$notes = $notesDao->getByAssoc(ASSOC_TYPE_QUERY, $query->getId())->toArray();

		$notesDoc = $exportFilter->execute($notes);
		if ($notesDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($notesDoc->documentElement, true);
			$queryNode->appendChild($clone);
		}
	}

	/**
	 * Add the QueryParticipants for a Query to its DOM element.
	 * @param $doc DOMDocument
	 * @param $reviewRoundNode DOMElement
	 * @param $query Query
	 */
	function addQueryParticipants($doc, $queryNode, $query) {
		$queryDao = DAORegistry::getDAO('QueryDAO');

		$participantIds = $queryDao->getParticipantIds($query->getId());

		$participantsNode = $this->processPraticipantIds($participantIds, $query);
		if ($participantsNode->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($participantsNode->documentElement, true);
			$queryNode->appendChild($clone);
		}
	}

	/**
	 * Create participants node
	 * @param $participantIds array of int
	 * @param $query Query
	 * @return DOMDocument
	 */
	function processPraticipantIds($participantIds, $query) {
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'participants');
		foreach ($participantIds as $participantId) {
			$rootNode->appendChild($this->createParticipantNode($doc, $participantId, $query));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	/**
	 * Create and return a review round file node.
	 * @param $doc DOMDocument
	 * @param $participantId int
	 * @param $query Query
	 * @return DOMElement
	 */
	function createParticipantNode($doc, $participantId, $query) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		$participantNode = $doc->createElementNS($deployment->getNamespace(), 'participant');

		$userDao = DAORegistry::getDAO('UserDAO');
		$participantUser = $userDao->getById($participantId);
		assert(isset($participantUser));
		$participantNode->setAttribute('username', $participantUser->getUsername());

		return $participantNode;
	}
}

?>
