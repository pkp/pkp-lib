<?php

/**
 * @file controllers/grid/settings/submissionChecklist/SubmissionChecklistGridRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionChecklistGridRow
 * @ingroup controllers_grid_settings_submissionChecklist
 *
 * @brief Handle submissionChecklist grid row requests.
 */

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class SubmissionChecklistGridRow extends GridRow
{
    //
    // Overridden template methods
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $rowId = $this->getId();
        if (isset($rowId) && is_numeric($rowId)) {
            $router = $request->getRouter();
            $actionArgs = [
                'gridId' => $this->getGridId(),
                'rowId' => $rowId
            ];

            $this->addAction(
                new LinkAction(
                    'editSubmissionChecklist',
                    new AjaxModal(
                        $router->url($request, null, null, 'editItem', null, $actionArgs),
                        __('grid.action.edit'),
                        'modal_edit',
                        true
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );

            $this->addAction(
                new LinkAction(
                    'deleteSubmissionChecklist',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('grid.action.delete'),
                        $router->url($request, null, null, 'deleteItem', null, $actionArgs),
                        'modal_delete'
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }
}
