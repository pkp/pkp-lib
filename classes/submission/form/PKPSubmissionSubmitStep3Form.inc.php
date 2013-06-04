<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep3Form.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep3Form
 * @ingroup submission_form
 *
 * @brief Form for Step 3 of author submission: submission metadata
 */

import('lib.pkp.classes.submission.form.SubmissionSubmitForm');

class PKPSubmissionSubmitStep3Form extends SubmissionSubmitForm {

	/** @var SubmissionMetadataFormImplementation */
	var $_metadataFormImplem;

	/**
	 * Constructor.
	 */
	function PKPSubmissionSubmitStep3Form($context, $submission, $metadataFormImplementation) {
		parent::SubmissionSubmitForm($context, $submission, 3);

		$this->_metadataFormImplem = $metadataFormImplementation;
		$this->_metadataFormImplem->addChecks($submission);
	}

	/**
	 * Initialize form data from current submission.
	 */
	function initData() {
		$this->_metadataFormImplem->initData($this->submission);
		return parent::initData();
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->_metadataFormImplem->readInputData();
	}

	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		$this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Assign the default participants.
	 * @param $submission Submission
	 * @param $request PKPRequest
	 */
	function assignDefaultParticipants($submission, $request) {
		// May be overridden by subclasses
	}

	/**
	 * Save changes to submission.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return int the submission ID
	 */
	function execute($args, $request) {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, $request);

		// Get an updated version of the submission.
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->submissionId);

		// Set other submission data.
		if ($submission->getSubmissionProgress() <= $this->step) {
			$submission->setDateSubmitted(Core::getCurrentDate());
			$submission->stampStatusModified();
			$submission->setSubmissionProgress(0);
		}

		// Save the submission.
		$submissionDao->updateObject($submission);

		$this->assignDefaultParticipants($submission, $request);

		//
		// Send a notification to associated users
		//

		$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */

		// Get the managers.
		$managers = $roleDao->getUsersByRoleId(ROLE_ID_MANAGER, $submission->getContextId());

		$managersArray = $managers->toAssociativeArray();

		$allUserIds = array_keys($managersArray);

		$notificationManager = new NotificationManager();
		foreach ($allUserIds as $userId) {
			$notificationManager->createNotification(
				$request, $userId, NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
				$submission->getContextId(), ASSOC_TYPE_SUBMISSION, $submission->getId()
			);

			// Add TASK notification indicating that a submission is unassigned
			$notificationManager->createNotification(
				$request,
				$userId,
				NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
				$submission->getContextId(),
				ASSOC_TYPE_SUBMISSION,
				$submission->getId(),
				NOTIFICATION_LEVEL_TASK
			);
		}

		$notificationManager->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_APPROVE_SUBMISSION),
			null,
			ASSOC_TYPE_SUBMISSION,
			$submission->getId()
		);

		return $this->submissionId;
	}
}

?>
