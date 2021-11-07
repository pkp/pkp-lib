<?php

/**
 * @file classes/controllers/grid/CategoryGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridDataProvider
 * @ingroup classes_controllers_grid
 *
 * @brief Provide access to category grid data. Can optionally use a grid data
 * provider object that already provides access to data that the grid needs.
 */

namespace PKP\controllers\grid;

class CategoryGridDataProvider extends GridDataProvider
{
    /** @var GridDataProvider A grid data provider that can be
     * used by this category grid data provider to provide access
     * to common data.
     */
    public $_dataProvider;


    //
    // Getters and setters.
    //
    /**
     * Get a grid data provider object.
     *
     * @return GridDataProvider
     */
    public function getDataProvider()
    {
        return $this->_dataProvider;
    }

    /**
     * Set a grid data provider object.
     *
     * @param GridDataProvider $dataProvider
     */
    public function setDataProvider($dataProvider)
    {
        if ($dataProvider instanceof self) {
            assert(false);
            $dataProvider = null;
        }

        $this->_dataProvider = $dataProvider;
    }


    //
    // Overriden methods from GridDataProvider
    //
    /**
     * @see GridDataProvider::setAuthorizedContext()
     */
    public function setAuthorizedContext(&$authorizedContext)
    {
        // We need to pass the authorized context object to
        // the grid data provider object, if any.
        $dataProvider = $this->getDataProvider();
        if ($dataProvider) {
            $dataProvider->setAuthorizedContext($authorizedContext);
        }

        parent::setAuthorizedContext($authorizedContext);
    }


    //
    // Template methods to be implemented by subclasses
    //
    /**
     * Retrieve the category data to load into the grid.
     *
     * @param PKPRequest $request
     * @param array|null $filter
     *
     * @return array
     */
    public function loadCategoryData($request, $categoryDataElement, $filter = null)
    {
        assert(false);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\CategoryGridDataProvider', '\CategoryGridDataProvider');
}
