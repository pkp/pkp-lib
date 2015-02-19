<?php

/**
 * @file controllers/grid/files/final/form/ManageFinalDraftFilesForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageFinalDraftFilesForm
 * @ingroup controllers_grid_files_finalDraftFiles
 *
 * @brief Form to add files to the final draft files grid
 */

import('lib.pkp.controllers.grid.files.form.ManageSubmissionFilesForm');

class ManageFinalDraftFilesForm extends ManageSubmissionFilesForm {

	/**
	 * Constructor.
	 * @param $submissionId int Submission ID.
	 */
	function ManageFinalDraftFilesForm($submissionId) {
		parent::ManageSubmissionFilesForm($submissionId, 'controllers/grid/files/final/manageFinalDraftFiles.tpl');
	}


	//
	// Overridden template methods
	//
	/**
	 * Save Selection of Final Draft files
	 * @param $args array
	 * @param $request PKPRequest
	 * @return array a list of all submission files marked as "final".
	 */
	function execute($args, $request, $stageSubmissionFiles) {
		parent::execute($args, $request, $stageSubmissionFiles, SUBMISSION_FILE_FINAL);
	}
}

?>
