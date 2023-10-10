<?php

/**
 * @file classes/controllers/grid/feature/GeneralPagingFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GeneralPagingFeature
 *
 * @ingroup controllers_grid_feature
 *
 * @brief Base class that implements common functionality for
 * paging features.
 *
 */

namespace PKP\controllers\grid\feature;

use APP\core\Application;
use PKP\controllers\grid\GridHandler;
use PKP\core\ArrayItemIterator;
use PKP\core\ItemIterator;
use PKP\handler\PKPHandler;

class GeneralPagingFeature extends GridFeature
{
    /** @var ItemIterator */
    private $_itemIterator;

    /** @var int itemsPerPage */
    private $_itemsPerPage;

    /**
     * @see GridFeature::GridFeature()
     *
     * @param string $id Feature identifier.
     * @param null|int $itemsPerPage Optional Number of items to show at
     * the first time.
     * Constructor.
     */
    public function __construct($id, $itemsPerPage = null)
    {
        $this->_itemsPerPage = $itemsPerPage;
        parent::__construct($id);
    }


    //
    // Getters and setters.
    //
    /**
     * Get item iterator.
     *
     * @return ItemIterator
     */
    public function getItemIterator()
    {
        return $this->_itemIterator;
    }


    //
    // Extended GridFeature methods.
    //
    /**
     * @copydoc GridFeature::setOptions()
     */
    public function setOptions($request, $grid)
    {
        // Get the default items per page setting value.
        $rangeInfo = PKPHandler::getRangeInfo($request, $grid->getId());
        $iterator = $this->getItemIterator();
        $defaultItemsPerPage = $rangeInfo->getCount();

        // Check for a component level items per page setting.
        $componentItemsPerPage = $request->getUserVar($this->_getItemsPerPageParamName($grid->getId()));
        if (!$componentItemsPerPage) {
            $componentItemsPerPage = $this->_itemsPerPage;
        }

        if ($componentItemsPerPage) {
            $currentItemsPerPage = $componentItemsPerPage;
        } else {
            $currentItemsPerPage = $defaultItemsPerPage;
        }

        $this->addOptions([
            'itemsPerPageParamName' => $this->_getItemsPerPageParamName($grid->getId()),
            'defaultItemsPerPage' => $defaultItemsPerPage,
            'currentItemsPerPage' => $currentItemsPerPage,
            'itemsTotal' => $iterator->getCount(),
            'pageParamName' => PKPHandler::getPageParamName($grid->getId()),
            'currentPage' => $iterator->getPage()
        ]);

        parent::setOptions($request, $grid);
    }


    //
    // Hooks implementation.
    //
    /**
     * @copydoc GridFeature::gridInitialize()
     * The feature will know about the current filter
     * value so it can request grid refreshes keeping
     * the filter.
     */
    public function getGridDataElements($args)
    {
        $filter = $args['filter'];

        if (is_array($filter) && !empty($filter)) {
            $this->addOptions(['filter' => json_encode($filter)]);
        }
    }


    /**
     * @copydoc GridFeature::setGridDataElements()
     */
    public function setGridDataElements($args)
    {
        $grid = & $args['grid'];
        $data = & $args['data'];

        if (is_array($data)) {
            $request = Application::get()->getRequest();
            $rangeInfo = $grid->getGridRangeInfo($request, $grid->getId());
            $itemIterator = new ArrayItemIterator($data, $rangeInfo->getPage(), $rangeInfo->getCount());
            $this->_itemIterator = $itemIterator;
            $data = $itemIterator->toArray();
        } elseif ($data instanceof ItemIterator) {
            $this->_itemIterator = $data;
        }
    }

    /**
     * @copydoc GridFeature::getRequestArgs()
     */
    public function getRequestArgs($args)
    {
        $grid = $args['grid'];
        $requestArgs = & $args['requestArgs'];

        // Add paging info so grid actions will not lose paging context.
        // Only works if grid link actions use the getRequestArgs
        // returned content.
        $request = Application::get()->getRequest();
        $rangeInfo = $grid->getGridRangeInfo($request, $grid->getId());
        $requestArgs[GridHandler::getPageParamName($grid->getId())] = $rangeInfo->getPage();
        $requestArgs[$this->_getItemsPerPageParamName($grid->getId())] = $rangeInfo->getCount();
    }

    /**
     * @copydoc GridFeature::getGridRangeInfo()
     */
    public function getGridRangeInfo($args)
    {
        $request = $args['request'];
        $grid = $args['grid'];
        $rangeInfo = $args['rangeInfo'];

        // Add grid level items per page setting, if any.
        $itemsPerPage = $request->getUserVar($this->_getItemsPerPageParamName($grid->getId()));
        if ($this->_itemsPerPage) {
            $itemsPerPage = $this->_itemsPerPage;
        } // Feature config overrides.
        if ($itemsPerPage) {
            $rangeInfo->setCount($itemsPerPage);
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Get the range info items per page parameter name.
     *
     * @param string $rangeName
     *
     * @return string
     */
    private function _getItemsPerPageParamName($rangeName)
    {
        return $rangeName . 'ItemsPerPage';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\GeneralPagingFeature', '\GeneralPagingFeature');
}
