<?php

/**
 * @file controllers/grid/files/copyedit/CopyeditedFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopyeditedFilesGridDataProvider
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Provide access to copyedited files management.
 */


import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

class CopyeditedFilesGridDataProvider extends SubmissionFilesGridDataProvider {
	/**
	 * Constructor
	 */
	function CopyeditedFilesGridDataProvider() {
		parent::SubmissionFilesGridDataProvider(SUBMISSION_FILE_COPYEDIT);
		$this->setViewableOnly(true);
	}

	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc FilesGridDataProvider::getSelectAction()
	 */
	function getSelectAction($request) {
		import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');
		return new SelectFilesLinkAction(
			$request,
			array(
				'submissionId' => $this->getSubmission()->getId(),
				'stageId' => $this->getStageId()
			),
			__('editor.submission.uploadSelectFiles')
		);
	}
}

?>
