<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlReviewAssignmentFilter.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlReviewAssignmentFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of review assignments
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewAssignmentFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML review assignment import');
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
		return 'review assignments';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'review assignment';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlReviewAssignmentFilter';
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

		$reviewRoundId = $deployment->getProcessedObjectsIds(ASSOC_TYPE_REVIEW_ROUND);

		// Create the data object
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment = $reviewAssignmentDao->newDataObject();
		$reviewAssignment->setSubmissionId($submission->getId());
		$reviewAssignment->setStageId($node->getAttribute('stage_id'));
		$reviewAssignment->setReviewRoundId($reviewRoundId[0]);
		if ($dateAssigned = $node->getAttribute('date_assigned')){
			$reviewAssignment->setDateAssigned(strtotime($dateAssigned));
		}

		if ($dateNotified = $node->getAttribute('date_notified')){
			$reviewAssignment->setDateNotified(strtotime($dateNotified));
		}

		if ($dateConfirmed = $node->getAttribute('date_confirmed')){
			$reviewAssignment->setDateConfirmed(strtotime($dateConfirmed));
		}

		if ($dateCompleted = $node->getAttribute('date_completed')){
			$reviewAssignment->setDateCompleted(strtotime($dateCompleted));
		}

		if ($dateAcknowledged = $node->getAttribute('date_acknowledged')){
			$reviewAssignment->setDateAcknowledged(strtotime($dateAcknowledged));
		}

		if ($dateDue = $node->getAttribute('date_due')){
			$reviewAssignment->setDateDue(strtotime($dateDue));
		}

		if ($dateResponseDue = $node->getAttribute('date_response_due')){
			$reviewAssignment->setDateResponseDue(strtotime($dateResponseDue));
		}

		if ($lastModified = $node->getAttribute('last_modified')){
			$reviewAssignment->setLastModified(strtotime($lastModified));
		}

		if ($dateRated = $node->getAttribute('date_rated')){
			$reviewAssignment->setDateRated(strtotime($dateRated));
		}

		if ($dateReminded = $node->getAttribute('date_reminded')){
			$reviewAssignment->setDateReminded(strtotime($dateReminded));
		}

		if ($node->getAttribute('reminder_was_automatic') == 'true'){
			$reviewAssignment->setReminderWasAutomatic(1);
		}

		if ($node->getAttribute('declined') == 'true'){
			$reviewAssignment->setDeclined(1);
		}

		if ($node->getAttribute('unconsidered') == 'true'){
			$reviewAssignment->setUnconsidered(1);
		}

		if ($recommendation = $node->getAttribute('recommendation')){
			$reviewAssignment->setRecommendation($recommendation);
		}

		if ($competingInterests = $node->getAttribute('competing_interests')){
			$reviewAssignment->setCompetingInterests($competingInterests);
		}

		if ($quality = $node->getAttribute('quality')){
			$reviewAssignment->setQuality($quality);
		}

		if ($round = $node->getAttribute('round')){
			$reviewAssignment->setRound($round);
		}

		if ($reviewMethod = $node->getAttribute('review_method')){
			$reviewAssignment->setReviewMethod($reviewMethod);
		}

		if ($reviewStep = $node->getAttribute('step')){
			$reviewAssignment->setStep($reviewStep);
		}

		$reviewerUsername = $node->getAttribute('reviewer');
		if (!$reviewerUsername) {
			$user = $deployment->getUser();
		} else {
			// Determine the user based on the username
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getByUsername($reviewerUsername);
		}
		if ($user) {
			$reviewAssignment->setReviewerId($user->getId());
		} else {
			$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownReviewer', array('param' => $reviewerUsername)));
			$errorOccured = true;
		}

		if ($errorOccured) {
			// if error occured, the file cannot be inserted into DB, becase
			// genre, uploader and user group are required (e.g. at name generation).
			$reviewAssignment = null;
		} else {
			$reviewAssignmentDao->insertObject($reviewAssignment);
		}

		// Handle subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				switch($n->tagName) {
					case 'reviewFiles':
						$this->parseReviewFiles($n, $reviewAssignment);
						break;
				}
			}
		}

		return $reviewAssignment;
	}

	/**
	 * Parse an reviewAssignments element
	 * @param $node DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewFiles($node, $reviewAssignment) {
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				assert($n->tagName == 'reviewFile');
				$this->parseReviewFile($n, $reviewAssignment);
			}
		}
	}

	/**
	 * Parse an author and add it to the submission.
	 * @param $n DOMElement
	 * @param $reviewRound ReviewRound
	 */
	function parseReviewFile($n, $reviewAssignment) {
		$deployment = $this->getDeployment();

		$oldFileId = $n->getAttribute('oldFileId');

		$newFileId = $deployment->getFileDBId($oldFileId);

		$reviewFileDao = DAORegistry::getDAO('ReviewFilesDAO');
		$reviewFileDao->grant($reviewAssignment->getId(), $newFileId);
	}
}

?>
