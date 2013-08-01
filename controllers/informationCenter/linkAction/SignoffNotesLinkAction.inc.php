<?php
/**
 * @file controllers/informationCenter/linkAction/SignoffNotesLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffNotesLinkAction
 * @ingroup controllers_informationCenter
 *
 * @brief An action to open the signoff history modal.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class SignoffNotesLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $signoff Signoff The signoff that will
	 * be used to get notes from.
	 * @param $submissionId int The signoff submission id.
	 * @param $stageId int The signoff stage id.
	 */
	function SignoffNotesLinkAction($request, $signoff, $submissionId, $stageId) {
		// Instantiate the redirect action request.
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$actionArgs = array(
			'signoffId' => $signoff->getId(),
			'submissionId' => $submissionId,
			'stageId' => $stageId
		);

		$user = $request->getUser();
		$router = $request->getRouter();

		$ajaxModal = new AjaxModal(
			$dispatcher->url($request, ROUTE_COMPONENT, null, 'informationCenter.SignoffInformationCenterHandler',
				'viewNotes', null, $actionArgs),
			__('submission.informationCenter.notes')
		);

		parent::LinkAction('viewSignoffNotes', $ajaxModal, '', $this->_getNoteState($signoff, $user));
	}

	/**
	 * Get the signoff note state.
	 * @param $signoff Signoff
	 * @param $user User
	 */
	function _getNoteState($signoff, $user) {
		$noteDao = DAORegistry::getDAO('NoteDAO');

		// If no notes exist, display a dimmed icon.
		if (!$noteDao->notesExistByAssoc(ASSOC_TYPE_SIGNOFF, $signoff->getId())) {
			return 'notes_none';
		}

		// If new notes exist, display a bold icon.
		if ($noteDao->unreadNotesExistByAssoc(ASSOC_TYPE_SIGNOFF, $signoff->getId(), $user->getId())) {
			return 'notes_new';
		}

		// Otherwise, notes exist but not new ones.
		return 'notes';
	}
}

?>
