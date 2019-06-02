<?php

/**
 * @file classes/controllers/modals/submissionMetadata/SubmissionMetadataHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionMetadataHandler
 * @ingroup classes_controllers_modals_submissionMetadata
 *
 * @brief Base class for submission metadata view/edit operations
 */

import('classes.handler.Handler');

// import JSON class for use with all AJAX requests
import('lib.pkp.classes.core.JSONMessage');

class SubmissionMetadataHandler extends Handler {

	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		$this->setupTemplate($request);
	}


	//
	// Public handler methods
	//
	/**
	 * Display the submission's metadata
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function fetch($args, $request, $params = null) {
		// Identify the submission
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);

		// Identify the stage, if we have one.
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		// Form handling
		$submissionMetadataViewForm = $this->getFormInstance($submission->getId(), $stageId, $params);

		$submissionMetadataViewForm->initData();

		return new JSONMessage(true, $submissionMetadataViewForm->fetch($request));
	}

	/**
	 * Save the submission metadata form.
	 * @param $args array
	 * @param $request Request
	 */
	function saveForm($args, $request) {
		$submissionId = $request->getUserVar('submissionId');

		// Form handling
		$submissionMetadataViewForm = $this->getFormInstance($submissionId);

		// Try to save the form data.
		$submissionMetadataViewForm->readInputData();
		if($submissionMetadataViewForm->validate()) {
			$submissionMetadataViewForm->execute();
			// Create trivial notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.savedSubmissionMetadata')));
			return new JSONMessage();
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Get an instance of the metadata form to be used by this handler.
	 * @param $submissionId int
	 * @return Form
	 */
	function getFormInstance($submissionId, $stageId = null, $params = null) {
		// N.B.: All subclasses (within each application) currently
		// use the same form class, but as it's app-dependent, we
		// can't put the instantiation higher up the class tree.
		assert(false); // To be implemented by subclasses
	}

	/**
	 * Get allow metadata edit permissions for submission, current user and stageId
	 * @param $submissionId int
	 * @param $currentUserId int
	 * @param $stageId int
	 * @return boolean true if the user is allowed to edit metadata
	 */
	static function getUserAllowEditMetadata($submissionId, $currentUserId, $stageId) {
		/** @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignmentsArray = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $currentUserId, $stageId);
		$stageAssignments = $stageAssignmentsArray->toArray();

		/** @var $stageAssignment StageAssignment */
		foreach($stageAssignments as $stageAssignment) {
			if ($stageAssignment->getCanChangeMetadata()) {
				return true;
			}
		}

		return false;
	}
}


