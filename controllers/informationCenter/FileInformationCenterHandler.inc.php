<?php

/**
 * @file controllers/informationCenter/FileInformationCenterHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileInformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Handle requests to view the information center for a file.
 */

import('lib.pkp.controllers.informationCenter.InformationCenterHandler');
import('lib.pkp.classes.core.JSONMessage');
import('classes.log.SubmissionEventLogEntry');

class FileInformationCenterHandler extends InformationCenterHandler {
	/** @var $submissionFile object */
	var $submissionFile;

	/** @var $submission object */
	var $submission;

	/**
	 * Constructor
	 */
	function FileInformationCenterHandler() {
		parent::InformationCenterHandler();

		$this->addRoleAssignment(
			array(
				ROLE_ID_AUTHOR,
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array('listPastNotes', 'listPastHistory')
		);
	}

	/**
	 * Fetch and store away objects
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Fetch the submission and file to display information about
		$this->submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$this->submissionFile = $submissionFileDao->getLatestRevision($request->getUserVar('fileId'));

		// Ensure data integrity.
		if (!$this->submission || !$this->submissionFile || $this->submission->getId() != $this->submissionFile->getSubmissionId()) fatalError('Unknown or invalid submission or submission file!');
	}

	/**
	 * Display the main information center modal.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function viewInformationCenter($args, $request) {
		$this->setupTemplate($request);

		// Assign variables to the template manager and display
		$templateMgr = TemplateManager::getManager($request);
		$fileName = (($s = $this->submissionFile->getLocalizedName()) != '') ? $s : __('common.untitled');
		if (($i = $this->submissionFile->getRevision()) > 1) $fileName .= " ($i)"; // Add revision number to label
		if (empty($fileName)) $fileName = __('common.untitled');
		$templateMgr->assign('title', $fileName);
		$templateMgr->assign('removeHistoryTab', (int) $request->getUserVar('removeHistoryTab'));

		return parent::viewInformationCenter($request);
	}

	/**
	 * Display the notes tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function viewNotes($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
		$notesForm = new NewFileNoteForm($this->submissionFile->getFileId());
		$notesForm->initData();

		$json = new JSONMessage(true, $notesForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Display the list of existing notes from prior files.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function listPastNotes($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$noteDao = DAORegistry::getDAO('NoteDAO');

		$submissionFile = $this->submissionFile;
		$notes = array();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		while (true) {
			$submissionFile = $submissionFileDao->getRevision($submissionFile->getSourceFileId(), $submissionFile->getSourceRevision());
			if (!$submissionFile) break;

			$iterator = $noteDao->getByAssoc($this->_getAssocType(), $submissionFile->getFileId());
			$notes += $iterator->toArray();
		}
		import('lib.pkp.classes.core.ArrayItemIterator');
		$templateMgr->assign('notes', new ArrayItemIterator($notes));

		$user = $request->getUser();
		$templateMgr->assign('currentUserId', $user->getId());

		$templateMgr->assign('notesListId', 'pastNotesList');
		return $templateMgr->fetchJson('controllers/informationCenter/notesList.tpl');
	}

	/**
	 * Save a note.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function saveNote($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
		$notesForm = new NewFileNoteForm($this->submissionFile->getFileId());
		$notesForm->readInputData();

		if ($notesForm->validate()) {
			$notesForm->execute($request);
			$json = new JSONMessage(true);

			// Save to event log
			$this->_logEvent($request, SUBMISSION_LOG_NOTE_POSTED);

			$user = $request->getUser();
			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.addedNote')));
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
	function viewNotify ($args, $request) {
		$this->setupTemplate($request);

		import('controllers.informationCenter.form.InformationCenterNotifyForm');
		$notifyForm = new InformationCenterNotifyForm($this->submissionFile->getFileId(), ASSOC_TYPE_SUBMISSION_FILE);
		$notifyForm->initData();

		$json = new JSONMessage(true, $notifyForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Send a notification from the notify tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function sendNotification ($args, $request) {
		$this->setupTemplate($request);

		import('controllers.informationCenter.form.InformationCenterNotifyForm');
		$notifyForm = new InformationCenterNotifyForm($this->submissionFile->getFileId(), ASSOC_TYPE_SUBMISSION_FILE);
		$notifyForm->readInputData($request);

		if ($notifyForm->validate()) {
			$noteId = $notifyForm->execute($request);

			$this->_logEvent($request, SUBMISSION_LOG_MESSAGE_SENT);
			$user = $request->getUser();
			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.sentNotification')));

			// Success--Return a JSON string indicating so (will clear the form on return, and indicate success)
			$json = new JSONMessage(true);
		} else {
			// Failure--Return a JSON string indicating so
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

		// Get all submission file events
		$submissionFileEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO');
		$fileEvents = $submissionFileEventLogDao->getByFileId(
			$this->submissionFile->getFileId()
		);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('eventLogEntries', $fileEvents);
		$templateMgr->assign('historyListId', 'historyList');
		return $templateMgr->fetchJson('controllers/informationCenter/historyList.tpl');
	}

	/**
	 * Display the list of history log entries from prior files.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function listPastHistory($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$submissionFileEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO');

		$submissionFile = $this->submissionFile;
		$events = array();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		while (true) {
			$submissionFile = $submissionFileDao->getRevision($submissionFile->getSourceFileId(), $submissionFile->getSourceRevision());
			if (!$submissionFile) break;

			$iterator = $submissionFileEventLogDao->getByFileId($submissionFile->getFileId());
			$events += $iterator->toArray();
		}
		import('lib.pkp.classes.core.ArrayItemIterator');
		$templateMgr->assign('eventLogEntries', new ArrayItemIterator($events));

		$user = $request->getUser();
		$templateMgr->assign('currentUserId', $user->getId());
		$templateMgr->assign('historyListId', 'pastHistoryList');
		return $templateMgr->fetchJson('controllers/informationCenter/historyList.tpl');
	}

	/**
	 * Log an event for this file
	 * @param $request PKPRequest
	 * @param $eventType int SUBMISSION_LOG_...
	 */
	function _logEvent ($request, $eventType) {
		// Get the log event message
		switch($eventType) {
			case SUBMISSION_LOG_NOTE_POSTED:
				$logMessage = 'informationCenter.history.notePosted';
				break;
			case SUBMISSION_LOG_MESSAGE_SENT:
				$logMessage = 'informationCenter.history.messageSent';
				break;
			default:
				assert(false);
		}

		import('lib.pkp.classes.log.SubmissionFileLog');
		SubmissionFileLog::logEvent($request, $this->submissionFile, $eventType, $logMessage);
	}

	/**
	 * Get an array representing link parameters that subclasses
	 * need to have passed to their various handlers (i.e. submission ID to
	 * the delete note handler). Subclasses should implement.
	 */
	function _getLinkParams() {
		return array(
			'fileId' => $this->submissionFile->getFileId(),
			'submissionId' => $this->submission->getId(),
			'stageId' => $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE)
		);
	}

	/**
	 * Get the association ID for this information center view
	 * @return int
	 */
	function _getAssocId() {
		return $this->submissionFile->getFileId();
	}

	/**
	 * Get the association type for this information center view
	 * @return int
	 */
	function _getAssocType() {
		return ASSOC_TYPE_SUBMISSION_FILE;
	}

	/**
	 * Set up the template
	 * @param $request PKPRequest
	 */
	function setupTemplate($request) {
		// Provide access to notes from past revisions/file IDs
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('showEarlierEntries', true);

		// Get the latest history item to display in the header
		$submissionEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO');
		$fileEvents = $submissionEventLogDao->getByFileId($this->submissionFile->getFileId());
		$lastEvent = $fileEvents->next();
		if(isset($lastEvent)) {
			$templateMgr->assign('lastEvent', $lastEvent);

			// Get the user who created the last event.
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getById($lastEvent->getUserId());
			$templateMgr->assign('lastEventUser', $user);
		}

		return parent::setupTemplate($request);
	}
}

?>
