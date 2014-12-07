<?php

/**
 * @file controllers/informationCenter/SignoffInformationCenterHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffInformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Handle requests to view the information center for a file.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class SignoffInformationCenterHandler extends Handler {

	/** @var Ŝignoff */
	private $_signoff;

	/** @var int */
	private $_stageId;

	/** @var Submission */
	private $_submission;

	/**
	 * Constructor
	 */
	function SignoffInformationCenterHandler() {
		parent::Handler();

		$this->addRoleAssignment(
			array(
				ROLE_ID_AUTHOR,
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_MANAGER,
				ROLE_ID_ASSISTANT
			),
			array('viewSignoffHistory', 'viewNotes', 'getUserSignoffs', 'fetchNotesForm', 'saveNote', 'listNotes', 'uploadFile')
		);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Fetch and store information for later use
		$this->_submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$this->_stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$this->_signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		// Require stage access
		import('classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int) $request->getUserVar('stageId')));

		if ($request->getUserVar('signoffId')) {
			// Determine the access mode
			$router = $request->getRouter();

			// Require signoff access
			import('classes.security.authorization.SignoffAccessPolicy');
			$this->addPolicy(new SignoffAccessPolicy(
				$request, $args, $roleAssignments,
				$router->getRequestedOp($request)=='saveNote'?SIGNOFF_ACCESS_MODIFY:SIGNOFF_ACCESS_READ,
				$request->getUserVar('stageId')));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display a modal containing history for the signoff.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewSignoffHistory($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('signoff', $this->_signoff);

		return $templateMgr->fetchJson('controllers/informationCenter/signoffHistory.tpl');
	}

	/**
	 * Fetch the signoff notes modal content.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewNotes($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $this->_submission->getId());
		$templateMgr->assign('stageId', $this->_stageId);
		$templateMgr->assign('symbolic', (string) $request->getUserVar('symbolic'));
		$signoff = $this->_signoff;
		if ($signoff) {
			$templateMgr->assign('signoffId', $this->_signoff->getId());
		}
		return $templateMgr->fetchJson('controllers/informationCenter/signoffNotes.tpl');
	}

	/**
	 * Get the available signoffs associated with the user in request.
	 * @param $args
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function getUserSignoffs($args, $request) {
		$user = $request->getUser();
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$symbolic = (string) $request->getUserVar('symbolic');

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$signoffsFactory = $signoffDao->getAllBySymbolic($symbolic, ASSOC_TYPE_SUBMISSION_FILE, null, $user->getId());

		$signoffs = array();
		while ($signoff = $signoffsFactory->next()) { /* @var $signoff Signoff */
			if (!$signoff->getDateCompleted() && $signoff->getAssocType() == ASSOC_TYPE_SUBMISSION_FILE) {
				$submissionFile = $submissionFileDao->getLatestRevision($signoff->getAssocId()); /* @var $submissionFile SubmissionFile */
				if (is_a($submissionFile, 'SubmissionFile')) {
					if ($submissionFile->getSubmissionId() == $this->_submission->getId()) {
						$signoffs[$signoff->getId()] = $submissionFile->getLocalizedName();
					}
				} else {
					assert(false);
				}
			}
		}

		return new JSONMessage(true, $signoffs);
	}

	/**
	 * Fetch the signoff notes form.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function fetchNotesForm($args, $request) {
		$this->setupTemplate($request);

		import('lib.pkp.controllers.grid.files.fileSignoff.form.NewSignoffNoteForm');
		$notesForm = new NewSignoffNoteForm($this->_signoff->getId(), $this->_submission->getId(), $this->_signoff->getSymbolic(), $this->_stageId);
		$notesForm->initData();

		return new JSONMessage(true, $notesForm->fetch($request));
	}

	/**
	 * Save a signoff note.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function saveNote($args, $request) {
		$this->setupTemplate($request);
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		import('lib.pkp.controllers.grid.files.fileSignoff.form.NewSignoffNoteForm');
		$notesForm = new NewSignoffNoteForm($this->_signoff->getId(), $this->_submission->getId(), $this->_signoff->getSymbolic(), $this->_stageId);
		$notesForm->readInputData();

		if ($notesForm->validate()) {
			$notesForm->execute($request, $userRoles);
			return new JSONMessage(true);
		}
		return new JSONMessage(false);
	}

	/**
	 * List signoff notes.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function listNotes($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$notesFactory = $noteDao->getByAssoc(ASSOC_TYPE_SIGNOFF, $this->_signoff->getId());
		$notes = $notesFactory->toAssociativeArray();
		// Get any note files.
		$noteFilesDownloadLink = array();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /** @var $submissionFileDao SubmissionFileDAO */
		import('lib.pkp.controllers.api.file.linkAction.DownloadFileLinkAction');
		foreach ($notes as $noteId => $note) {
			$file = $submissionFileDao->getLatestRevisionsByAssocId(ASSOC_TYPE_NOTE, $noteId, $this->_submission->getId(), SUBMISSION_FILE_NOTE);
			// We don't expect more than one file per note
			$file = current($file);

			// Get the download file link action.
			if ($file) {
				$noteFilesDownloadLink[$noteId] = new DownloadFileLinkAction($request, $file, $this->_stageId);
			}
		}

		$user = $request->getUser();

		import('lib.pkp.classes.core.ArrayItemIterator');
		$templateMgr->assign('notes', new ArrayItemIterator($notes));
		$templateMgr->assign('noteFilesDownloadLink', $noteFilesDownloadLink);
		$templateMgr->assign('notesListId', 'notesList');
		$templateMgr->assign('currentUserId', $user->getId());
		$templateMgr->assign('notesDeletable', false);

		$json = new JSONMessage(true, $templateMgr->fetch('controllers/informationCenter/notesList.tpl'));
		$json->setEvent('dataChanged');
		return $json;
	}

	/**
	 * Upload a file and render the modified upload wizard.
	 * @param $args array
	 * @param $request Request
	 * @return string a serialized JSON object
	 */
	function uploadFile($args, $request) {
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFile->getId()
			));
			return $json;
		}
		return new JSONMessage(false, __('common.uploadFailed'));
	}


	//
	// Private functions
	//
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
				'stageId' => $this->_stageId,
			)
		);
	}
}

?>
