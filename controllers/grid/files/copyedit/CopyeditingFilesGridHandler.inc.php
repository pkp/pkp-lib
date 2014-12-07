<?php

/**
 * @file controllers/grid/files/copyedit/CopyeditingFilesGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopyeditingFilesGridHandler
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Subclass of file editor/auditor grid for copyediting files.
 */

// import grid signoff files grid base classes
import('controllers.grid.files.signoff.SignoffFilesGridHandler');

// Import submission file class which contains the SUBMISSION_FILE_* constants.
import('lib.pkp.classes.submission.SubmissionFile');

// Import SUBMISSION_EMAIL_* constants.
import('lib.pkp.classes.log.PKPSubmissionEmailLogEntry');

class CopyeditingFilesGridHandler extends SignoffFilesGridHandler {
	/**
	 * Constructor
	 */
	function CopyeditingFilesGridHandler() {
		parent::SignoffFilesGridHandler(
			WORKFLOW_STAGE_ID_EDITING,
			SUBMISSION_FILE_COPYEDIT,
			'SIGNOFF_COPYEDITING',
			SUBMISSION_EMAIL_COPYEDIT_NOTIFY_AUTHOR
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array(
				'approveCopyedit'
			)
		);
	}

	/**
	 * @copydoc SignoffFilesGridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		// Approve copyediting file needs submission access policy.
		$router = $request->getRouter();
		if ($router->getRequestedOp($request) == 'approveCopyedit') {
			import('classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}


	/**
	 * Configure the grid
	 * @param PKPRequest $request
	 */
	function initialize($request) {
		parent::initialize($request);

		$this->setTitle('submission.copyediting');
		$this->setInstructions('editor.submission.editorial.copyeditingDescription');

		// Basic grid configuration
		$this->setId('copyeditingFiles');
	}


	//
	// Public methods
	//
	/**
	 * Approve/disapprove the copyediting file, changing its visibility.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function approveCopyedit($args, $request) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		if ($submissionFile->getViewable()) {
			// No longer expose the file to be sent to next stage.
			$submissionFile->setViewable(false);
		} else {
			// Expose the file.
			$submissionFile->setViewable(true);
		}

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFileDao->updateObject($submissionFile);

		return DAO::getDataChangedEvent($submissionFile->getId());
	}
}

?>
