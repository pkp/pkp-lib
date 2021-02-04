<?php

/**
 * @file controllers/grid/files/final/form/ManageFinalDraftFilesForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	function __construct($submissionId) {
		parent::__construct($submissionId, 'controllers/grid/files/final/manageFinalDraftFiles.tpl');
	}


	//
	// Overridden template methods
	//
	/**
	 * Save Selection of Final Draft files
	 * @param $stageSubmissionFiles array The files that belongs to a file stage
	 * that is currently being used by a grid inside this form.
	 * @param $fileStage int SUBMISSION_FILE_...
	 * @return array a list of all submission files marked as "final".
	 */
	function execute($stageSubmissionFiles, $fileStage = null) {
		parent::execute($stageSubmissionFiles, SUBMISSION_FILE_FINAL);
	}
}


