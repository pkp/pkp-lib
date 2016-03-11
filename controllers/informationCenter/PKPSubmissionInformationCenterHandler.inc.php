<?php

/**
 * @file controllers/informationCenter/PKPSubmissionInformationCenterHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionInformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Handle requests to view the information center for a submission.
 */

import('lib.pkp.controllers.informationCenter.InformationCenterHandler');
import('lib.pkp.classes.core.JSONMessage');
import('classes.log.SubmissionEventLogEntry');

class PKPSubmissionInformationCenterHandler extends InformationCenterHandler {
	/**
	 * Constructor
	 */
	function PKPSubmissionInformationCenterHandler() {
		parent::InformationCenterHandler();
	}

	/**
	 * Display the metadata tab.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function metadata($args, $request) {
		$this->setupTemplate($request);

		import('controllers.modals.submissionMetadata.form.SubmissionMetadataViewForm');
		// prevent anyone but managers and editors from submitting the catalog entry form
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$params = array();
		if (!array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $userRoles)) {
			$params['hideSubmit'] = true;
			$params['readOnly'] = true;
		}
		$submissionMetadataViewForm = new SubmissionMetadataViewForm($this->_submission->getId(), null, $params);
		$submissionMetadataViewForm->initData($args, $request);

		return new JSONMessage(true, $submissionMetadataViewForm->fetch($request));
	}

	/**
	 * Save the metadata tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function saveForm($args, $request) {
		$this->setupTemplate($request);

		import('controllers.modals.submissionMetadata.form.SubmissionMetadataViewForm');
		$submissionMetadataViewForm = new SubmissionMetadataViewForm($this->_submission->getId());

		// Try to save the form data.
		$submissionMetadataViewForm->readInputData($request);
		if($submissionMetadataViewForm->validate()) {
			$submissionMetadataViewForm->execute($request);
			// Create trivial notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.savedSubmissionMetadata')));
			return new JSONMessage(true);
		}
		return new JSONMessage(false);
	}

	/**
	 * Display the main information center modal.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function viewInformationCenter($args, $request) {
		// Get the latest history item to display in the header
		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
		$submissionEvents = $submissionEventLogDao->getBySubmissionId($this->_submission->getId());
		$lastEvent = $submissionEvents->next();

		// Assign variables to the template manager and display
		$templateMgr = TemplateManager::getManager($request);
		if(isset($lastEvent)) {
			$templateMgr->assign('lastEvent', $lastEvent);

			// Get the user who posted the last note
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getById($lastEvent->getUserId());
			$templateMgr->assign('lastEventUser', $user);
		}

		return parent::viewInformationCenter($args, $request);
	}

	/**
	 * Display the notes tab.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewNotes($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewSubmissionNoteForm');
		$notesForm = new NewSubmissionNoteForm($this->_submission->getId());
		$notesForm->initData();

		return new JSONMessage(true, $notesForm->fetch($request));
	}

	/**
	 * Save a note.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function saveNote($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewSubmissionNoteForm');
		$notesForm = new NewSubmissionNoteForm($this->_submission->getId());
		$notesForm->readInputData();

		if ($notesForm->validate()) {
			$notesForm->execute($request);

			// Save to event log
			$user = $request->getUser();
			$userId = $user->getId();
			$this->_logEvent($request, SUBMISSION_LOG_NOTE_POSTED);

			return new JSONMessage(true);
		} else {
			// Return a JSON string indicating failure
			return new JSONMessage(false);
		}
	}

	/**
	 * Fetch the contents of the event log.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewHistory($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$dispatcher = $request->getDispatcher();
		$templateMgr->assign('gridParameters', $this->_getLinkParams());
		return $templateMgr->fetchJson('controllers/informationCenter/submissionHistory.tpl');
	}

	/**
	 * Get the association ID for this information center view
	 * @return int
	 */
	function _getAssocId() {
		return $this->_submission->getId();
	}

	/**
	 * Get the association type for this information center view
	 * @return int
	 */
	function _getAssocType() {
		return ASSOC_TYPE_SUBMISSION;
	}

	/**
	 * Log an event for this file
	 * @param $request PKPRequest
	 * @param $eventType SUBMISSION_LOG_...
	 */
	function _logEvent ($request, $eventType) {
		assert(false); // overridden in subclasses.
	}
}

?>
