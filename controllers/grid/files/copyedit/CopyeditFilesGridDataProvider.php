<?php

/**
 * @file controllers/grid/files/copyedit/CopyeditFilesGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CopyeditFilesGridDataProvider
 * @ingroup controllers_grid_files_copyedit
 *
 * @brief Provide access to copyedited files management.
 */

namespace PKP\controllers\grid\files\copyedit;

use PKP\controllers\grid\files\fileList\linkAction\SelectFilesLinkAction;
use PKP\controllers\grid\files\SubmissionFilesGridDataProvider;
use PKP\submissionFile\SubmissionFile;

class CopyeditFilesGridDataProvider extends SubmissionFilesGridDataProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(SubmissionFile::SUBMISSION_FILE_COPYEDIT);
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getSelectAction()
     */
    public function getSelectAction($request)
    {
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
