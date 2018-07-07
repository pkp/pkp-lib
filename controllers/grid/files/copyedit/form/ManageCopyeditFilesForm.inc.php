<?php

/**
 * @file controllers/grid/files/copyedit/form/ManageCopyeditFilesForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageCopyeditFilesForm
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Form to add files to the copyedited files grid
 */

import('lib.pkp.controllers.grid.files.form.ManageSubmissionFilesForm');

class ManageCopyeditFilesForm extends ManageSubmissionFilesForm {

	/**
	 * Constructor.
	 * @param $submissionId int Submission ID.
	 */
	function __construct($submissionId) {
		parent::__construct($submissionId, 'controllers/grid/files/copyedit/manageCopyeditFiles.tpl');
	}

	/**
	 * Save selection of copyedited files
	 * @param $stageSubmissionFiles array List of submission files in this stage.
	 * @param $fileStage int SUBMISSION_FILE_...
	 */
	function execute($stageSubmissionFiles, $fileStage = null) {
		parent::execute($stageSubmissionFiles, SUBMISSION_FILE_COPYEDIT);
	}
}

?>
