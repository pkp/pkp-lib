<?php

/**
 * @file controllers/grid/admin/context/ContextGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextGridCellProvider
 *
 * @ingroup controllers_grid_admin_context
 *
 * @brief Subclass for a context grid column's cell provider
 */

namespace PKP\controllers\grid\admin\context;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;

class ContextGridCellProvider extends GridCellProvider
{
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
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\context\Context && !empty($columnId));
        switch ($columnId) {
            case 'name':
                $label = $element->getLocalizedName() != '' ? $element->getLocalizedName() : __('common.untitled');
                return ['label' => $label];
            case 'urlPath':
                $label = $element->getPath();
                return ['label' => $label];
            default:
                break;
        }
    }
}
