<?php

/**
 * @file controllers/listbuilder/files/CopyeditingFilesListbuilderHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopyeditingFilesListbuilderHandler
 * @ingroup listbuilder
 *
 * @brief Class for selecting files to add a user to for copyediting.
 */

import('lib.pkp.controllers.listbuilder.files.FilesListbuilderHandler');

class CopyeditingFilesListbuilderHandler extends FilesListbuilderHandler {
	/**
	 * Constructor
	 */
	function CopyeditingFilesListbuilderHandler() {
		// Get access to the submission file constants.
		import('lib.pkp.classes.submission.SubmissionFile');
		parent::FilesListbuilderHandler(SUBMISSION_FILE_COPYEDIT);
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		return parent::authorize($request, $args, $roleAssignments, WORKFLOW_STAGE_ID_EDITING);
	}


	//
	// Implement methods from FilesListbuilderHandler
	//
	/**
	 * @copydoc FilesListbuilderHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);
		$this->setTitle('editor.submission.selectCopyedingFiles');
	}

	/**
	 * @copydoc FilesListbuilderHandler::getOptions()
	 */
	function getOptions() {
		import('lib.pkp.classes.submission.SubmissionFile');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $this->getFileStage());

		return parent::getOptions($submissionFiles);
	}
}

?>
