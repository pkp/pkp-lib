<?php

/**
 * @file controllers/grid/announcements/AnnouncementTypeGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeGridCellProvider
 * @ingroup controllers_grid_announcements
 *
 * @brief Cell provider for title column of an announcement type grid.
 */

namespace PKP\controllers\grid\announcements;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class AnnouncementTypeGridCellProvider extends GridCellProvider
{
    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        switch ($column->getId()) {
            case 'name':
                $announcementType = $row->getData();
                $router = $request->getRouter();
                $actionArgs = ['announcementTypeId' => $row->getId()];

                return [new LinkAction(
                    'edit',
                    new AjaxModal(
                        $router->url($request, null, null, 'editAnnouncementType', null, $actionArgs),
                        __('grid.action.edit'),
                        null,
                        true
                    ),
                    htmlspecialchars($announcementType->getLocalizedTypeName())
                )];
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $announcementType = $row->getData();
        $columnId = $column->getId();
        assert($announcementType instanceof \PKP\announcement\AnnouncementType && !empty($columnId));

        switch ($columnId) {
            case 'title':
                return ['label' => $announcementType->getLocalizedName()];
                break;
            default:
                break;
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }
}
