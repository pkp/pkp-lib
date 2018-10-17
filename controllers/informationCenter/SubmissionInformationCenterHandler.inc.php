<?php

/**
 * @file controllers/informationCenter/SubmissionInformationCenterHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionInformationCenterHandler
 * @ingroup controllers_informationCenter
 *
 * @brief Handle requests to view the information center for a submission.
 */

import('lib.pkp.controllers.informationCenter.InformationCenterHandler');
import('lib.pkp.classes.core.JSONMessage');
import('classes.log.SubmissionEventLogEntry');

class SubmissionInformationCenterHandler extends InformationCenterHandler {

	/** @var boolean Is the current user assigned to an editorial role for this submission */
	var $_isCurrentUserAssignedEditor;

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$success = parent::authorize($request, $args, $roleAssignments);

		// Prevent users from accessing history unless they are assigned to an
		// appropriate role in this submission
		$this->_isCurrentUserAssignedEditor = false;
		$userAssignedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		if (!empty($userAssignedRoles)) {
			foreach ($userAssignedRoles as $stageId => $roles) {
				if (array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $roles)) {
					$this->_isCurrentUserAssignedEditor = true;
					break;
				}
			}
		} else {
			$userGlobalRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
			if (array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userGlobalRoles)) {
				$this->_isCurrentUserAssignedEditor = true;
			}
		}

		if (!$this->_isCurrentUserAssignedEditor) {
			return false;
		}

		return $success;
	}

	/**
	 * @copydoc InformationCenterHandler::viewInformationCenter()
	 */
	function viewInformationCenter($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$user = $request->getUser();
		// Do not display the History tab if the user is not a manager or a sub-editor
		$userHasRole = $user->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR), $this->_submission->getContextId());
		$templateMgr->assign('removeHistoryTab', !$userHasRole || !$this->_isCurrentUserAssignedEditor);
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

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('notesList', $this->_listNotes($args, $request));

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
			$notesForm->execute();

			// Save to event log
			$this->_logEvent($request, $this->_submission, SUBMISSION_LOG_NOTE_POSTED, 'SubmissionLog');

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
}


