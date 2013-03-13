<?php
/**
 * @defgroup controllers_informationCenter_linkAction
 */

/**
 * @file controllers/informationCenter/linkAction/PKPFileInfoCenterLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFileInfoCenterLinkAction
 * @ingroup controllers_informationCenter_linkAction
 *
 * @brief A base action to open up the information center for a file.
 */

import('lib.pkp.controllers.api.file.linkAction.FileLinkAction');

class PKPFileInfoCenterLinkAction extends FileLinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionFile SubmissionFile the submission file
	 * to show information about.
	 * @param $stageId int (optional) The stage id that user is looking at.
	 */
	function PKPFileInfoCenterLinkAction(&$request, &$submissionFile, $stageId = null) {
		// Instantiate the information center modal.
		$ajaxModal = $this->getModal($request, $submissionFile, $stageId);

		// Configure the file link action.
		parent::FileLinkAction(
			'moreInformation', $ajaxModal,
			__('grid.action.moreInformation'), 'more_info'
		);
	}

	/**
	 * returns the modal for this link action.
	 * Must be overridden in subclasses.
	 * @param $request PKPRequest
	 * @param $submissionFile SubmissionFile
	 * @param $stageId int
	 * @return AjaxModal
	 */
	function getModal($request, $submissionFile, $stageId) {
		assert(false);
	}
}

?>
