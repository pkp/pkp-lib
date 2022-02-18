<?php
/**
 * @file controllers/grid/files/fileList/linkAction/SelectFilesLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectFilesLinkAction
 * @ingroup controllers_grid_files_fileList_linkAction
 *
 * @brief An abstract base action for actions to open up a modal that allows users to
 *  select files from a file list grid.
 */

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class SelectFilesLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param array $actionArgs The parameters required by the
     *  link action target to identify a list of files.
     * @param string $actionLabel The localized label of the link action.
     * @param string $modalTitle the (optional) title to be used for the modal.
     */
    public function __construct($request, $actionArgs, $actionLabel, $modalTitle = null)
    {
        // Create an ajax action request that'll contain
        // the file selection grid.
        $modalTitle ??= $actionLabel;
        $router = $request->getRouter();
        $ajaxModal = new AjaxModal(
            $router->url($request, null, null, 'selectFiles', null, $actionArgs),
            $modalTitle,
            'modal_add_file'
        );

        // Configure the link action.
        parent::__construct('selectFiles', $ajaxModal, $actionLabel, 'add');
    }
}
