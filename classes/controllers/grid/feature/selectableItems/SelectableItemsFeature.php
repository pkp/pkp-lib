<?php

/**
 * @file classes/controllers/grid/feature/selectableItems/SelectableItemsFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableItemsFeature
 *
 * @ingroup controllers_grid_feature_selectableItems
 *
 * @brief Implements grid widgets selectable items functionality.
 *
 */

namespace PKP\controllers\grid\feature\selectableItems;

use PKP\controllers\grid\feature\GridFeature;

class SelectableItemsFeature extends GridFeature
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('selectableItems');
    }


    //
    // Hooks implementation.
    //
    /**
     * @see GridFeature::gridInitialize()
     */
    public function gridInitialize($args)
    {
        $grid = $args['grid'];

        // Add checkbox column to the grid.
        $grid->addColumn(new ItemSelectionGridColumn($grid->getSelectName()));
    }

    /**
     * @see GridFeature::getInitializedRowInstance()
     */
    public function getInitializedRowInstance($args)
    {
        $grid = $args['grid'];
        $row = $args['row'];

        if ($grid instanceof \PKP\controllers\grid\CategoryGridHandler) {
            $categoryId = $grid->getCurrentCategoryId();
            $row->addFlag('selected', (bool) $grid->isDataElementInCategorySelected($categoryId, $row->getData()));
        } else {
            $row->addFlag('selected', (bool) $grid->isDataElementSelected($row->getData()));
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature', '\SelectableItemsFeature');
}
