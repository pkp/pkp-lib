<?php

/**
 * @file controllers/grid/files/final/form/ManageFinalDraftFilesForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageFinalDraftFilesForm
 *
 * @ingroup controllers_grid_files_finalDraftFiles
 *
 * @brief Form to add files to the final draft files grid
 */

namespace PKP\controllers\grid\files\final\form;

use PKP\controllers\grid\files\form\ManageSubmissionFilesForm;
use PKP\submissionFile\SubmissionFile;

class ManageFinalDraftFilesForm extends ManageSubmissionFilesForm
{
    /**
     * Constructor.
     *
     * @param int $submissionId Submission ID.
     */
    public function __construct($submissionId)
    {
        parent::__construct($submissionId, 'controllers/grid/files/final/manageFinalDraftFiles.tpl');
    }


    //
    // Overridden template methods
    //
    /**
     * Save Selection of Final Draft files
     *
     * @param array $stageSubmissionFiles The files that belongs to a file stage
     * that is currently being used by a grid inside this form.
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     */
    public function execute($stageSubmissionFiles = null, $fileStage = null, ...$functionArgs)
    {
        parent::execute($stageSubmissionFiles, SubmissionFile::SUBMISSION_FILE_FINAL);
    }
}
