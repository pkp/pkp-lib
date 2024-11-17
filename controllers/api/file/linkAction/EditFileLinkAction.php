<?php
/**
 * @file controllers/api/file/linkAction/EditFileLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditFileLinkAction
 *
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to edit a file's metadata.
 */

namespace PKP\controllers\api\file\linkAction;

use APP\core\Request;
use PKP\core\PKPApplication;
use PKP\linkAction\request\AjaxModal;
use PKP\submissionFile\SubmissionFile;

class EditFileLinkAction extends FileLinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param SubmissionFile $submissionFile the submission file to edit.
     * @param int $stageId Stage ID
     */
    public function __construct($request, $submissionFile, $stageId)
    {
        // Instantiate the AJAX modal request.
        $router = $request->getRouter();
        $dispatcher = $router->getDispatcher();
        $modal = new AjaxModal(
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'api.file.ManageFileApiHandler',
                'editMetadata',
                null,
                $this->getActionArgs($submissionFile, $stageId)
            ),
            __('grid.action.editFile'),
        );

        // Configure the file link action.
        parent::__construct(
            'editFile',
            $modal,
            __('common.edit'),
            'edit'
        );
    }
}
