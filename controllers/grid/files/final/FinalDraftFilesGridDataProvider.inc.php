<?php

/**
 * @file controllers/grid/files/final/FinalDraftFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FinalDraftFilesGridDataProvider
 * @ingroup controllers_grid_files_final
 *
 * @brief Provide access to final draft files management.
 */

use PKP\submissionFile\SubmissionFile;

import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

class FinalDraftFilesGridDataProvider extends SubmissionFilesGridDataProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(SubmissionFile::SUBMISSION_FILE_FINAL);
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getSelectAction()
     */
    public function getSelectAction($request)
    {
        import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');
        return new SelectFilesLinkAction(
            $request,
            [
                'submissionId' => $this->getSubmission()->getId(),
                'stageId' => $this->getStageId()
            ],
            __('editor.submission.uploadSelectFiles')
        );
    }
}
