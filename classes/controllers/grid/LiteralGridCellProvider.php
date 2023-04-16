<?php

/**
 * @file classes/controllers/grid/LiteralGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LiteralGridCellProvider
 *
 * @ingroup controllers_grid
 *
 * @brief A cell provider that passes literal data through directly.
 */

namespace PKP\controllers\grid;

class LiteralGridCellProvider extends GridCellProvider
{
    //
    // Template methods from GridCellProvider
    //
    /**
     * This implementation assumes a data element that is a literal value.
     * If desired, the 'id' column can be used to present the row ID.
     *
     * @see GridCellProvider::getTemplateVarsFromRowColumn()
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        switch ($column->getId()) {
            case 'id':
                return ['label' => $row->getId()];
            case 'value':
            default:
                return ['label' => $row->getData()];
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\LiteralGridCellProvider', '\LiteralGridCellProvider');
}
