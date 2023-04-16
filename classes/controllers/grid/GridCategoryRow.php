<?php

/**
 * @file classes/controllers/grid/GridCategoryRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryRow
 *
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling the category row in a grid
 *
 */

namespace PKP\controllers\grid;

class GridCategoryRow extends GridRow
{
    /** @var string empty row locale key */
    public $_emptyCategoryRowText = 'grid.noItems';

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Set a default cell provider that will get the cell template
        // variables from the category grid row.
        $this->setCellProvider(new GridCategoryRowCellProvider());
    }


    //
    // Getters/Setters
    //
    /**
     * Get the no items locale key
     */
    public function getEmptyCategoryRowText()
    {
        return $this->_emptyCategoryRowText;
    }

    /**
     * Set the no items locale key
     */
    public function setEmptyCategoryRowText($emptyCategoryRowText)
    {
        $this->_emptyCategoryRowText = $emptyCategoryRowText;
    }

    /**
     * Category rows only have one cell and one label.  This is it.
     */
    public function getCategoryLabel()
    {
        return '';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\GridCategoryRow', '\GridCategoryRow');
}
