<?php

/**
 * @file controllers/grid/settings/genre/GenreGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreGridRow
 * @ingroup controllers_grid_settings_genre
 *
 * @brief Handle Genre grid row requests.
 */

namespace PKP\controllers\grid\settings\genre;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class GenreGridRow extends GridRow
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
                'genreId' => $rowId
            ];

            $this->addAction(
                new LinkAction(
                    'editGenre',
                    new AjaxModal(
                        $router->url($request, null, null, 'editGenre', null, $actionArgs),
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
                    'deleteGenre',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('grid.action.delete'),
                        $router->url($request, null, null, 'deleteGenre', null, $actionArgs),
                        'modal_delete'
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }
}
