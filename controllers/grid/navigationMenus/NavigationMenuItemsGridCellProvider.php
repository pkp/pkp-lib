<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridCellProvider
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief Cell provider for title column of a NavigationMenuItems grid.
 */

namespace PKP\controllers\grid\navigationMenus;

use APP\core\Application;
use APP\core\Services;
use APP\template\TemplateManager;
use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;

class NavigationMenuItemsGridCellProvider extends GridCellProvider
{
    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
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
        $navigationMenuItem = $row->getData();
        $columnId = $column->getId();
        assert($navigationMenuItem instanceof \PKP\navigationMenu\NavigationMenuItem && !empty($columnId));

        switch ($columnId) {
            case 'title':
                $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
                Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

                return ['label' => $navigationMenuItem->getLocalizedTitle()];
            default:
                break;
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }
}
