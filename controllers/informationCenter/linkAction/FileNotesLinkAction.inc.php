<?php
/**
 * @file controllers/informationCenter/linkAction/FileNotesLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileNotesLinkAction
 * @ingroup controllers_informationCenter_linkAction
 *
 * @brief An action to open up the notes IC for a file.
 */

import('lib.pkp.controllers.api.file.linkAction.FileLinkAction');

class FileNotesLinkAction extends FileLinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionFile SubmissionFile the submission file
	 *  to show information about.
	 * @param $user User
	 * @param $stageId int (optional) The stage id that user is looking at.
	 * @param $removeHistoryTab boolean (optional) Open the information center
	 * without the history tab.
	 */
	function FileNotesLinkAction($request, $submissionFile, $user, $stageId = null, $removeHistoryTab = false) {
		// Instantiate the information center modal.
		$ajaxModal = $this->getModal($request, $submissionFile, $stageId, $removeHistoryTab);

		// Configure the file link action.
		parent::FileLinkAction(
			'moreInformation', $ajaxModal,
			'', $this->getNotesState($submissionFile, $user),
			__('common.notes.tooltip')
		);
	}

	function getNotesState($submissionFile, $user) {
		$noteDao = DAORegistry::getDAO('NoteDAO');

		// If no notes exist, display a dimmed icon.
		if (!$noteDao->notesExistByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId())) {
			return 'notes_none';
		}

		// If new notes exist, display a bold icon.
		if ($noteDao->unreadNotesExistByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), $user->getId())) {
			return 'notes_new';
		}

		// Otherwise, notes exist but not new ones.
		return 'notes';
	}

	/**
	 * returns the modal for this link action.
	 * Must be overridden in subclasses.
	 * @param $request PKPRequest
	 * @param $submissionFile SubmissionFile
	 * @param $stageId int
	 * @param $removeHistoryTab boolean
	 * @return AjaxModal
	 */
	function getModal($request, $submissionFile, $stageId, $removeHistoryTab) {
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $request->getRouter();

		$title = (isset($submissionFile)) ? implode(': ', array(__('informationCenter.informationCenter'), $submissionFile->getLocalizedName())) : __('informationCenter.informationCenter');

		$ajaxModal = new AjaxModal(
			$router->url(
				$request, null,
				'informationCenter.FileInformationCenterHandler', 'viewInformationCenter',
				null, array_merge($this->getActionArgs($submissionFile, $stageId), array('removeHistoryTab' => $removeHistoryTab))
			),
			$title,
			'modal_information'
		);

		return $ajaxModal;
	}
}

?>
