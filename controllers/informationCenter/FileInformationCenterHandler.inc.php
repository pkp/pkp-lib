<?php

/**
 * @file controllers/informationCenter/FileInformationCenterHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
	/** @var object */
	var $submissionFile;

	/** @var int */
	var $_stageId;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_ASSISTANT),
			array(
				'viewInformationCenter',
				'viewHistory',
				'viewNotes', 'listNotes', 'saveNote', 'deleteNote',
			)
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		// Require stage access
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int) $request->getUserVar('stageId')));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Fetch and store away objects
	 * @param $request PKPRequest
	 * @param $args array optional
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		$this->_stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$this->submissionFile = $submissionFileDao->getLatestRevision($request->getUserVar('fileId'));

		// Ensure data integrity.
		if (!$this->_submission || !$this->submissionFile || $this->_submission->getId() != $this->submissionFile->getSubmissionId()) fatalError('Unknown or invalid submission or submission file!');
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
		$templateMgr->assign('removeHistoryTab', (int) $request->getUserVar('removeHistoryTab'));

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

		import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
		$notesForm = new NewFileNoteForm($this->submissionFile->getFileId());
		$notesForm->initData();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('notesList', $this->_listNotes($args, $request));
		$templateMgr->assign('pastNotesList', $this->_listPastNotes($args, $request));

		return new JSONMessage(true, $notesForm->fetch($request));
	}

	/**
	 * Display the list of existing notes from prior files.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function _listPastNotes($args, $request) {
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
		$templateMgr->assign(array(
			'currentUserId' => $user->getId(),
			'notesListId' => 'pastNotesList',
			'notesDeletable' => false,
		));

		return $templateMgr->fetch('controllers/informationCenter/notesList.tpl');
	}

	/**
	 * Save a note.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function saveNote($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.informationCenter.form.NewFileNoteForm');
		$notesForm = new NewFileNoteForm($this->submissionFile->getFileId());
		$notesForm->readInputData();

		if ($notesForm->validate()) {
			$notesForm->execute();

			// Save to event log
			$this->_logEvent($request, $this->submissionFile, SUBMISSION_LOG_NOTE_POSTED, 'SubmissionFileLog');

			$user = $request->getUser();
			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.addedNote')));

			$jsonViewNotesResponse = $this->viewNotes($args, $request);
			$json = new JSONMessage(true);
			$json->setEvent('dataChanged');
			$json->setEvent('noteAdded', $jsonViewNotesResponse->_content);

			return $json;

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
		return $templateMgr->fetchAjax(
			'eventLogGrid',
			$dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.eventLog.SubmissionFileEventLogGridHandler', 'fetchGrid', null, $this->_getLinkParams())
		);
	}

	/**
	 * Get an array representing link parameters that subclasses
	 * need to have passed to their various handlers (i.e. submission ID
	 * to the delete note handler).
	 * @return array
	 */
	function _getLinkParams() {
		return array_merge(
			parent::_getLinkParams(),
			array(
				'fileId' => $this->submissionFile->getFileId(),
				'stageId' => $this->_stageId,
			)
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
		$templateMgr = TemplateManager::getManager($request);

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
