<?php

/**
 * @file controllers/grid/settings/contributorRoles/ContributorRoleGridRow.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleGridRow
 *
 * @ingroup controllers_grid_settings_contributorRoles
 *
 * @brief Handle contributor role grid row requests.
 */

namespace PKP\controllers\grid\settings\contributorRoles;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class ContributorRoleGridRow extends GridRow
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
        if (!empty($rowId) && is_numeric($rowId)) {
            $router = $request->getRouter();
            $actionArgs = [
                'gridId' => $this->getGridId(),
                'rowId' => $rowId,
            ];

            $this->addAction(
                new LinkAction(
                    'editContributorRole',
                    new AjaxModal(
                        $router->url($request, null, null, 'editContributorRole', null, $actionArgs),
                        __('grid.action.edit'),
                        null,
                        true
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );

            $this->addAction(
                new LinkAction(
                    'deleteContributorRole',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('grid.action.delete'),
                        $router->url($request, null, null, 'deleteContributorRole', null, $actionArgs),
                        'negative'
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }
}
