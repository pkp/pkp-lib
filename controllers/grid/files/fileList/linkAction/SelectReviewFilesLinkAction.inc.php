<?php
/**
 * @file controllers/grid/files/fileList/linkAction/SelectReviewFilesLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectReviewFilesLinkAction
 * @ingroup controllers_grid_files_fileList_linkAction
 *
 * @brief An action to open up the modal that allows users to select review files
 *  from a file list grid.
 */

import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectFilesLinkAction');

class SelectReviewFilesLinkAction extends SelectFilesLinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param ReviewRound $reviewRound The review round from which to
     *  select review files.
     * @param string $actionLabel The localized label of the link action.
     * @param string $modalTitle the (optional) title to be used for the modal.
     */
    public function __construct($request, $reviewRound, $actionLabel, $modalTitle = null)
    {
        $actionArgs = ['submissionId' => $reviewRound->getSubmissionId(),
            'stageId' => $reviewRound->getStageId(), 'reviewRoundId' => $reviewRound->getId()];

        parent::__construct($request, $actionArgs, $actionLabel, $modalTitle);
    }
}
