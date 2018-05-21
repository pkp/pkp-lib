<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlReviewRoundFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlReviewRoundFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of review rounds
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewRoundFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review round import');
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
		return 'review rounds';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'review round';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlReviewRoundFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of PKPAuthor objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		// Create the data object
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewRound = $reviewRoundDao->newDataObject();
		$reviewRound->setSubmissionId($submission->getId());
		$reviewRound->setStageId($node->getAttribute('stage_id'));
		$reviewRound->setRound($node->getAttribute('round'));
		$reviewRound->setStatus($node->getAttribute('status'));

		// $deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownUserGroup', array('param' => $userGroupName)));
		$reviewRoundInserted = $reviewRoundDao->insertObject($reviewRound);
		$deployment->addProcessedObjectId(ASSOC_TYPE_REVIEW_ROUND, $reviewRoundInserted->getId());

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'reviewAssignments':
						$this->parseReviewAssignments($n, $reviewRound);
						break;
					case 'reviewRoundFiles':
						$this->parseReviewRoundFiles($n, $reviewRound);
						break;
				}
			}
		}

		return $reviewRound;
	}

	/**
	 * Parse an reviewAssignments element
	 * @param $node DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewAssignments($node, $reviewRound) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'reviewAssignment');
				$this->parseReviewAssignment($n, $reviewRound);
			}
		}
	}

	/**
	 * Parse an author and add it to the submission.
	 * @param $n DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewAssignment($n, $reviewRound) {
		$filterDao = DAORegistry::getDAO('FilterDAO');
		$importFilters = $filterDao->getObjectsByGroup('native-xml=>review-assignment');
		assert(count($importFilters)==1); // Assert only a single unserialization filter
		$importFilter = array_shift($importFilters);
		$importFilter->setDeployment($this->getDeployment());
		$reviewAssignmentDoc = new DOMDocument();
		$reviewAssignmentDoc->appendChild($reviewAssignmentDoc->importNode($n, true));
		return $importFilter->execute($reviewAssignmentDoc);
	}

	/**
	 * Parse an reviewAssignments element
	 * @param $node DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewRoundFiles($node, $reviewRound) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'reviewRoundFile');
				$this->parseReviewRoundFile($n, $reviewRound);
			}
		}
	}

	/**
	 * Parse an author and add it to the submission.
	 * @param $n DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewRoundFile($n, $reviewRound) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		$oldFileId = $n->getAttribute('oldFileId');
		$revision = $n->getAttribute('revision');

		$newFileId = $deployment->getFileDBId($oldFileId, $revision);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFileDao->assignRevisionToReviewRound($newFileId, $revision, $reviewRound);
	}
}

?>
