<?php

/**
 * @file controllers/grid/announcements/AnnouncementTypeGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeGridRow
 *
 * @ingroup controllers_grid_content_announcements
 *
 * @brief Announcement type grid row definition
 */

namespace PKP\controllers\grid\announcements;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class AnnouncementTypeGridRow extends GridRow
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

        // Is this a new row or an existing row?
        $element = $this->getData();
        assert($element instanceof \PKP\announcement\AnnouncementType);

        $rowId = $this->getId();

        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = [
                'announcementTypeId' => $rowId
            ];
            $this->addAction(
                new LinkAction(
                    'edit',
                    new AjaxModal(
                        $router->url($request, null, null, 'editAnnouncementType', null, $actionArgs),
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
                    'remove',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('common.remove'),
                        $router->url($request, null, null, 'deleteAnnouncementType', null, $actionArgs),
                        'negative'
                    ),
                    __('grid.action.remove'),
                    'delete'
                )
            );
        }
    }
}
