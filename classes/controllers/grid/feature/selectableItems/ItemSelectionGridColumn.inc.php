<?php
/**
 * @file classes/controllers/grid/feature/selectableItems/ItemSelectionGridColumn.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ItemSelectionGridColumn
 * @ingroup classes_controllers_grid_feature_selectableItems
 *
 * @brief Implements a column with checkboxes to select grid items.
 */

namespace PKP\controllers\grid\feature\selectableItems;

use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\GridColumn;

class ItemSelectionGridColumn extends GridColumn
{
    /** @var string */
    public $_selectName;


    /**
     * Constructor
     *
     * @param string $selectName The name of the form parameter
     *  to which the selected files will be posted.
     */
    public function __construct($selectName)
    {
        assert(is_string($selectName) && !empty($selectName));
        $this->_selectName = $selectName;

        $cellProvider = new ColumnBasedGridCellProvider();
        parent::__construct(
            'select',
            'common.select',
            null,
            'controllers/grid/gridRowSelectInput.tpl',
            $cellProvider,
            ['width' => 3]
        );
    }


    //
    // Getters and Setters
    //
    /**
     * Get the select name.
     *
     * @return string
     */
    public function getSelectName()
    {
        return $this->_selectName;
    }


    //
    // Public methods
    //
    /**
     * Method expected by ColumnBasedGridCellProvider
     * to render a cell in this column.
     *
     * @see ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRow($row)
    {
        // Return the data expected by the column's cell template.
        return [
            'elementId' => $row->getId(),
            'selectName' => $this->getSelectName(),
            'selected' => $row->getFlag('selected')];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\selectableItems\ItemSelectionGridColumn', '\ItemSelectionGridColumn');
}
