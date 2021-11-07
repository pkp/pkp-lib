<?php
/**
 * @file controllers/api/file/linkAction/DeleteFileLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeleteFileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to delete a file.
 */

import('lib.pkp.controllers.api.file.linkAction.FileLinkAction');

use PKP\linkAction\request\RemoteActionConfirmationModal;

class DeleteFileLinkAction extends FileLinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param SubmissionFile $submissionFile the submission file to be deleted
     * @param int $stageId (optional)
     * @param string $localeKey (optional) Locale key to use for delete link
     *  be deleted.
     */
    public function __construct($request, $submissionFile, $stageId, $localeKey = 'grid.action.delete')
    {
        $router = $request->getRouter();
        parent::__construct(
            'deleteFile',
            new RemoteActionConfirmationModal(
                $request->getSession(),
                __('common.confirmDelete'),
                __('common.delete'),
                $router->url(
                    $request,
                    null,
                    'api.file.ManageFileApiHandler',
                    'deleteFile',
                    null,
                    $this->getActionArgs($submissionFile, $stageId)
                ),
                'modal_delete'
            ),
            __($localeKey),
            'delete'
        );
    }
}
