<?php

/**
 * @file controllers/informationCenter/PKPSubmissionInformationCenterHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
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
	/** @var $_submission Submission */
	var $_submission;

	/**
	 * Constructor
	 */
	function SubmissionInformationCenterHandler() {
		parent::InformationCenterHandler();
	}

	/**
	 * Fetch and store away objects
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Fetch the submission to display information about
		$this->_submission =& $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Display the metadata tab.
	 * @param $args array
	 * @param $request PKPRequest
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

		$json = new JSONMessage(true, $submissionMetadataViewForm->fetch($request));
		return $json->getString();
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

		$json = new JSONMessage();

		// Try to save the form data.
		$submissionMetadataViewForm->readInputData($request);
		if($submissionMetadataViewForm->validate()) {
			$submissionMetadataViewForm->execute($request);
			// Create trivial notification.
			$notificationManager = new NotificationManager();
			$user = $request->getUser();
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.savedSubmissionMetadata')));
		} else {
			$json->setStatus(false);
		}

		return $json->getString();
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
			$templateMgr->assign_by_ref('lastEvent', $lastEvent);

			// Get the user who posted the last note
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getById($lastEvent->getUserId());
			$templateMgr->assign_by_ref('lastEventUser', $user);
		}

		return parent::viewInformationCenter($request);
	}

	/**
	 * Display the notes tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function viewNotes($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewSubmissionNoteForm');
		$notesForm = new NewSubmissionNoteForm($this->_submission->getId());
		$notesForm->initData();

		$json = new JSONMessage(true, $notesForm->fetch($request));
		return $json->getString();
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
			$json = new JSONMessage(true);

			// Save to event log
			$user = $request->getUser();
			$userId = $user->getId();
			$this->_logEvent($request, SUBMISSION_LOG_NOTE_POSTED);
		} else {
			// Return a JSON string indicating failure
			$json = new JSONMessage(false);
		}

		return $json->getString();
	}

	/**
	 * Display the notify tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function viewNotify($args, $request) {
		$this->setupTemplate($request);

		import('controllers.informationCenter.form.InformationCenterNotifyForm'); // exists in each app.
		$notifyForm = new InformationCenterNotifyForm($this->_submission->getId(), ASSOC_TYPE_SUBMISSION);
		$notifyForm->initData();

		$json = new JSONMessage(true, $notifyForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Fetches an email template's message body and returns it via AJAX.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fetchTemplateBody($args, $request) {
		assert(false); // overridden in subclasses.
	}

	/**
	 * Send a notification from the notify tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function sendNotification ($args, $request) {
		$this->setupTemplate($request);

		import('controllers.informationCenter.form.InformationCenterNotifyForm');
		$notifyForm = new InformationCenterNotifyForm($this->_submission->getId(), ASSOC_TYPE_SUBMISSION);
		$notifyForm->readInputData($request);

		if ($notifyForm->validate()) {
			$noteId = $notifyForm->execute($request);
			// Return a JSON string indicating success
			// (will clear the form on return)
			$json = new JSONMessage(true);

			$this->_logEvent($request, SUBMISSION_LOG_MESSAGE_SENT);
			// Create trivial notification.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('informationCenter.history.messageSent')));
		} else {
			// Return a JSON string indicating failure
			$json = new JSONMessage(false);
		}

		return $json->getString();
	}

	/**
	 * Fetch the contents of the event log.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function listHistory($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		// Get all submission events
		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
		$submissionEvents = $submissionEventLogDao->getBySubmissionId($this->_submission->getId());
		$templateMgr->assign('eventLogEntries', $submissionEvents);
		$templateMgr->assign('historyListId', 'historyList');
		return $templateMgr->fetchJson('controllers/informationCenter/historyList.tpl');
	}

	/**
	 * Get an array representing link parameters that subclasses
	 * need to have passed to their various handlers (i.e. submission ID to
	 * the delete note handler). Subclasses should implement.
	 */
	function _getLinkParams() {
		return array('submissionId' => $this->_submission->getId());
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
