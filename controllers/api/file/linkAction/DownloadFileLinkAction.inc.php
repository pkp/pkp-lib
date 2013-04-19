<?php
/**
 * @file controllers/api/file/linkAction/DownloadFileLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DownloadFileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to download a file.
 */

import('lib.pkp.controllers.api.file.linkAction.FileLinkAction');

class DownloadFileLinkAction extends FileLinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionFile SubmissionFile the submission file to
	 *  link to.
	 * @param $stageId int (optional)
	 */
	function DownloadFileLinkAction($request, $submissionFile, $stageId = null) {
		// Instantiate the redirect action request.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.PostAndRedirectAction');
		$redirectRequest = new PostAndRedirectAction(
			$router->url(
				$request, null, 'api.file.FileApiHandler', 'recordDownload',
				null, $this->getActionArgs($submissionFile, $stageId)),
			$router->url(
				$request, null, 'api.file.FileApiHandler', 'downloadFile',
				null, $this->getActionArgs($submissionFile, $stageId))
		);

		// Configure the file link action.
		parent::FileLinkAction(
			'downloadFile', $redirectRequest, $this->getLabel($submissionFile),
			$submissionFile->getDocumentType()
		);
	}

	/**
	 * Get the label for the file download action.
	 * @param $submissionFile SubmissionFile
	 * @return string
	 */
	function getLabel(&$submissionFile) {
		return $submissionFile->getFileLabel();
	}
}

?>
