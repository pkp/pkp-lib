<?php

/**
 * @file plugins/importexport/native/filter/ReviewAssignmentNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of review assignments to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class ReviewAssignmentNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review assignments export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.ReviewAssignmentNativeXmlFilter';
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $reviewAssignments array Array of ReviewAssignment
	 * @return DOMDocument
	 */
	function &process(&$reviewAssignments) {
		// Create the XML document
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		// Wrap in a <reviewAssignments> element
		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'reviewAssignments');
		foreach ($reviewAssignments as $reviewAssignment) {
			$rootNode->appendChild($this->createReviewAssignmentsNode($doc, $reviewAssignment));
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
	 * Create and return an reviewAssignment node.
	 * @param $doc DOMDocument
	 * @param $reviewAssignment ReviewAssignment
	 * @return DOMElement
	 */
	function createReviewAssignmentsNode($doc, $reviewAssignment) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the reviewAssignment node
		$reviewAssignmentNode = $doc->createElementNS($deployment->getNamespace(), 'reviewAssignment');

		if ($dateAssigned = $reviewAssignment->getDateAssigned()) {
			$reviewAssignmentNode->setAttribute('date_assigned', strftime('%Y-%m-%d', strtotime($dateAssigned)));
		}

		if ($dateNotified = $reviewAssignment->getDateNotified()) {
			$reviewAssignmentNode->setAttribute('date_notified', strftime('%Y-%m-%d', strtotime($dateNotified)));
		}

		if ($dateConfirmed = $reviewAssignment->getDateConfirmed()) {
			$reviewAssignmentNode->setAttribute('date_confirmed', strftime('%Y-%m-%d', strtotime($dateConfirmed)));
		}

		if ($dateCompleted = $reviewAssignment->getDateCompleted()) {
			$reviewAssignmentNode->setAttribute('date_completed', strftime('%Y-%m-%d', strtotime($dateCompleted)));
		}

		if ($dateAcknowledged = $reviewAssignment->getDateAcknowledged()) {
			$reviewAssignmentNode->setAttribute('date_acknowledged', strftime('%Y-%m-%d', strtotime($dateAcknowledged)));
		}

		if ($dateDue = $reviewAssignment->getDateDue()) {
			$reviewAssignmentNode->setAttribute('date_due', strftime('%Y-%m-%d', strtotime($dateDue)));
		}

		if ($dateResponseDue = $reviewAssignment->getDateResponseDue()) {
			$reviewAssignmentNode->setAttribute('date_response_due', strftime('%Y-%m-%d', strtotime($dateResponseDue)));
		}

		if ($dateLastModified = $reviewAssignment->getLastModified()) {
			$reviewAssignmentNode->setAttribute('last_modified', strftime('%Y-%m-%d', strtotime($dateLastModified)));
		}

		if ($dateRated = $reviewAssignment->getDateRated()) {
			$reviewAssignmentNode->setAttribute('date_rated', strftime('%Y-%m-%d', strtotime($dateRated)));
		}

		if ($dateReminded = $reviewAssignment->getDateReminded()) {
			$reviewAssignmentNode->setAttribute('date_reminded', strftime('%Y-%m-%d', strtotime($dateReminded)));
		}

		if ($reviewAssignment->getReminderWasAutomatic()) {
			$reviewAssignmentNode->setAttribute('reminder_was_automatic', 'true');
		}

		if ($reviewAssignment->getDeclined()) {
			$reviewAssignmentNode->setAttribute('declined', 'true');
		}

		if ($reviewAssignment->getUnconsidered()) {
			$reviewAssignmentNode->setAttribute('unconsidered', 'true');
		}

		if ($recommentation = $reviewAssignment->getRecommendation()) {
			$reviewAssignmentNode->setAttribute('recommendation', $recommentation);
		}

		if ($competingInterests = $reviewAssignment->getCompetingInterests()) {
			$reviewAssignmentNode->setAttribute('competing_interests', $competingInterests);
		}

		if ($quality = $reviewAssignment->getQuality()) {
			$reviewAssignmentNode->setAttribute('quality', $quality);
		}

		if ($round = $reviewAssignment->getRound()) {
			$reviewAssignmentNode->setAttribute('round', $round);
		}

		if ($reviewMethod = $reviewAssignment->getReviewMethod()) {
			$reviewAssignmentNode->setAttribute('review_method', $reviewMethod);
		}

		if ($reviewAssignmentStageId = $reviewAssignment->getStageId()) {
			$reviewAssignmentNode->setAttribute('stage_id', $reviewAssignmentStageId);
		}

		if ($reviewAssignmentStep = $reviewAssignment->getStep()) {
			$reviewAssignmentNode->setAttribute('step', $reviewAssignmentStep);
		}

		$userDao = DAORegistry::getDAO('UserDAO');
		$reviewerUser = $userDao->getById($reviewAssignment->getReviewerId());
		assert(isset($reviewerUser));
		$reviewAssignmentNode->setAttribute('reviewer', $reviewerUser->getUsername());

		$this->addReviewFiles($doc, $reviewAssignmentNode, $reviewAssignment);

		return $reviewAssignmentNode;
	}

	/**
	 * Add the ReviewRoundFiles for a review round to its DOM element.
	 * @param $doc DOMDocument
	 * @param $reviewAssignmentNode DOMElement
	 * @param $reviewAssignment ReviewAssignment
	 */
	function addReviewFiles($doc, $reviewAssignmentNode, $reviewAssignment) {
		$fileDao = DAORegistry::getDAO('ReviewFilesDAO');

		$reviewFiles = $fileDao->getByReviewId($reviewAssignment->getId());

		$reviewFilesNode = $this->processReviewFiles($reviewFiles, $reviewAssignment);
		if ($reviewFilesNode->documentElement instanceof DOMElement) {
			$clone = $doc->importNode($reviewFilesNode->documentElement, true);
			$reviewAssignmentNode->appendChild($clone);
		}
	}

	/**
	 * Create reviewFiles Node
	 * @param $reviewFiles array of SubmissionFiles
	 * @param $reviewAssignment ReviewAssignment
	 * @return DOMDocument
	 */
	function processReviewFiles($reviewFiles, $reviewAssignment) {
		$doc = new DOMDocument('1.0');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();

		$rootNode = $doc->createElementNS($deployment->getNamespace(), 'reviewFiles');
		foreach ($reviewFiles as $reviewFile) {
			$rootNode->appendChild($this->createReviewFileNode($doc, $reviewFile, $reviewAssignment));
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	/**
	 * Create review file node node.
	 * @param $doc DOMDocument
	 * @param $reviewFileId int
	 * @return DOMElement
	 */
	function createReviewFileNode($doc, $reviewFileId) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the reviewAssignment node
		$reviewFileNode = $doc->createElementNS($deployment->getNamespace(), 'reviewFile');

		if ($reviewFileId) {
			$reviewFileNode->setAttribute('oldFileId', $reviewFileId);
		}

		return $reviewFileNode;
	}
}

?>
