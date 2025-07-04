<?php

/**
 * @file classes/controllers/grid/ColumnBasedGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ColumnBasedGridCellProvider
 *
 * @ingroup controllers_grid
 *
 * @brief A cell provider that relies on the column implementation
 *  to provide cell content. Use this cell provider if you have complex
 *  column-specific content. If you want to provide simple labels then
 *  use the ArrayGridCellProvider or DataObjectGridCellProvider.
 *
 * @see ArrayGridCellProvider
 * @see DataObjectGridCellProvider
 */

namespace PKP\controllers\grid;

class ColumnBasedGridCellProvider extends GridCellProvider
{
    //
    // Implement protected template methods from GridCellProvider
    //
    /**
     * @see GridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        // Delegate to the column to provide template variables.
        return $column->getTemplateVarsFromRow($row);
    }
}
