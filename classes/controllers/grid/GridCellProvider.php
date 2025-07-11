<?php

/**
 * @file classes/controllers/grid/GridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridCellProvider
 *
 * @ingroup controllers_grid
 *
 * @brief Base class for a grid column's cell provider.
 *
 * Grid cell providers provide formatted data to grid columns.
 * For general information about grids, see GridHandler.
 */

namespace PKP\controllers\grid;

use APP\core\Request;
use APP\template\TemplateManager;
use PKP\facades\Locale;

class GridCellProvider
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    //
    // Public methods
    //

    /**
     * To be used by a GridRow to generate a rendered representation of
     * the element for the given column.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return string the rendered representation of the element for the given column
     */
    public function render($request, $row, $column)
    {
        $columnId = $column->getId();
        assert(!empty($columnId));

        // Construct a default cell id (null for "nonexistent" new rows)
        $rowId = $row->getId(); // Potentially null (indicating row not backed in the DB)
        $cellId = isset($rowId) ? $rowId . '-' . $columnId : null;

        // Assign values extracted from the element for the cell.
        $templateMgr = TemplateManager::getManager($request);
        $templateVars = $this->getTemplateVarsFromRowColumn($row, $column);
        foreach ($templateVars as $varName => $varValue) {
            $templateMgr->assign($varName, $varValue);
        }
        $templateMgr->assign([
            'id' => $cellId,
            'column' => $column,
            'actions' => $this->getCellActions($request, $row, $column),
            'flags' => $column->getFlags(),
            'formLocales' => Locale::getSupportedFormLocales(),
        ]);
        $template = $column->getTemplate();
        assert(!empty($template));
        return $templateMgr->fetch($template);
    }

    //
    // Protected template methods
    //
    /**
     * Subclasses have to implement this method to extract variables
     * for a given column from a data element so that they may be assigned
     * to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        return [];
    }

    /**
     * Subclasses can override this template method to provide
     * cell specific actions.
     *
     * NB: The default implementation delegates to the grid column for
     * cell-specific actions. Another thinkable implementation would
     * be row-specific actions in which case action instantiation
     * should be delegated to the row.
     *
     * @param Request $request
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     * @param int $position GRID_ACTION_POSITION_...
     *
     * @return array an array of LinkAction instances
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        return $column->getCellActions($request, $row, $position);
    }
}
