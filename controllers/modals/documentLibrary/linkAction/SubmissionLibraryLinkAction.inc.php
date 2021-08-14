<?php

/**
 * @file controllers/grid/files/submissionDocuments/SubmissionLibraryLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLibraryLinkAction
 * @ingroup controllers_grid_files_submissionDocuments
 *
 * @brief An action to open up the submission documents modal.
 */

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class SubmissionLibraryLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId the ID of the submission to present link for
     * to show information about.
     */
    public function __construct($request, $submissionId)
    {
        $dispatcher = $request->getDispatcher();
        parent::__construct(
            'editorialHistory',
            new AjaxModal(
                $dispatcher->url(
                    $request,
                    PKPApplication::ROUTE_COMPONENT,
                    null,
                    'modals.documentLibrary.DocumentLibraryHandler',
                    'documentLibrary',
                    null,
                    ['submissionId' => $submissionId]
                ),
                __('editor.submissionLibrary'),
                'modal_information'
            ),
            __('editor.submissionLibrary'),
            'more_info'
        );
    }
}
