<?php
/**
 * @file controllers/grid/settings/reviewForms/ReviewFormElementGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementGridCellProvider
 *
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Subclass for review form element column's cell provider
 */

namespace PKP\controllers\grid\settings\reviewForms;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;

class ReviewFormElementGridCellProvider extends GridCellProvider
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
        assert($element instanceof \PKP\reviewForm\ReviewFormElement && !empty($columnId));
        switch ($columnId) {
            case 'question':
                $label = $element->getLocalizedQuestion();
                return ['label' => $label];
            default:
                assert(false);
                break;
        }
    }
}
