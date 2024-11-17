<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridRow
 *
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief NavigationMenuItem grid row definition
 */

namespace PKP\controllers\grid\navigationMenus;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class NavigationMenuItemsGridRow extends GridRow
{
    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $element = $this->getData();
        assert($element instanceof \PKP\navigationMenu\NavigationMenuItem);

        $rowId = $this->getId();

        // Is this a new row or an existing row?
        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = [
                'navigationMenuItemId' => $rowId
            ];

            $this->addAction(
                new LinkAction(
                    'edit',
                    new AjaxModal(
                        $router->url($request, null, null, 'editNavigationMenuItem', null, $actionArgs),
                        __('grid.action.edit'),
                        'side-modal',
                        true
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );

            $this->addAction(
                new LinkAction(
                    'remove',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('common.remove'),
                        $router->url($request, null, null, 'deleteNavigationMenuItem', null, $actionArgs),
                        'negative'
                    ),
                    __('grid.action.remove'),
                    'delete'
                )
            );
        }
    }
}
