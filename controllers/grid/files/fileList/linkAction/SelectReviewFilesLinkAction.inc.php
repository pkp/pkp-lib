<?php
/**
 * @file controllers/grid/files/fileList/linkAction/SelectReviewFilesLinkAction.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectReviewFilesLinkAction
 * @ingroup controllers_grid_files_fileList_linkAction
 *
 * @brief An action to open up the modal that allows users to select review files
 *  from a file list grid.
 */

import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');

class SelectReviewFilesLinkAction extends SelectFilesLinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $reviewRound ReviewRound The review round from which to
	 *  select review files.
	 * @param $actionLabel string The localized label of the link action.
	 * @param $modalTitle string the (optional) title to be used for the modal.
	 */
	function SelectReviewFilesLinkAction($request, $reviewRound, $actionLabel, $modalTitle = null) {
		$actionArgs = array('submissionId' => $reviewRound->getSubmissionId(),
				'stageId' => $reviewRound->getStageId(), 'reviewRoundId' => $reviewRound->getId());

		parent::SelectFilesLinkAction($request, $actionArgs, $actionLabel, $modalTitle);
	}
}

?>
