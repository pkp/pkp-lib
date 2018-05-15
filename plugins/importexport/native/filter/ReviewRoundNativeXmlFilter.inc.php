<?php

/**
 * @file plugins/importexport/native/filter/PKPAuthorNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of authors to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewRoundNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review round export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.ReviewRoundNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $reviewRounds array Array of ReviewRounds
	 * @return DOMDocument
	 */
	function &process(&$reviewRounds) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Multiple authors; wrap in a <authors> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'reviewRounds');
		foreach ($reviewRounds as $reviewRound) {
			$rootNode->appendChild($this->createReviewRoundNode($doc, $reviewRound));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// PKPAuthor conversion functions
	//
	/**
	 * Create and return an reviewRound node.
	 * @param $doc DOMDocument
	 * @param $reviewRound ReviewRound
	 * @return DOMElement
	 */
	function createReviewRoundNode($doc, $reviewRound) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the reviewRound node
		$reviewRoundNode = $doc->createElementNS($deployment->getNamespace(), 'reviewRound');

		$reviewRoundNode->setAttribute('stage_id', $reviewRound->getStageId());
		$reviewRoundNode->setAttribute('round', $reviewRound->getRound());
		$reviewRoundNode->setAttribute('status', $reviewRound->getStatus());

		$this->addReviewAssignments($doc, $reviewRoundNode, $reviewRound);

		return $reviewRoundNode;
	}

	/**
	 * Add the addReviewAssignments for a submission to its DOM element.
	 * @param $doc DOMDocument
	 * @param $reviewRoundNode DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function addReviewAssignments($doc, $reviewRoundNode, $reviewRound) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$nativeExportFilters = $filterDao->getObjectsByGroup('review-assignment=>native-xml');
		assert(count($nativeExportFilters)==1); // Assert only a single serialization filter
		$exportFilter = array_shift($nativeExportFilters);
		$exportFilter->setDeployment($this->getDeployment());

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignments = $reviewAssignmentDao->getByReviewRoundId($reviewRound->getId());

		$reviewAssignmentsDoc = $exportFilter->execute($reviewAssignments);
		if ($reviewAssignmentsDoc->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewAssignmentsDoc->documentElement, true);
			$reviewRoundNode->appendChild($clone);
		}
	}
}

?>
