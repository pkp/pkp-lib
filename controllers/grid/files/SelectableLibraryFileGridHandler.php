<?php

/**
 * @file controllers/grid/files/SelectableLibraryFileGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableLibraryFileGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Handle selectable library file list category grid requests.
 */

namespace PKP\controllers\grid\files;

use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;
use PKP\controllers\grid\settings\library\LibraryFileAdminGridDataProvider;

class SelectableLibraryFileGridHandler extends LibraryFileGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(new LibraryFileAdminGridDataProvider(true));
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature()];
    }

    /**
     * @copydoc GridHandler::isDataElementInCategorySelected()
     */
    public function isDataElementInCategorySelected($categoryDataId, &$gridDataElement)
    {
        return false;
    }

    /**
     * Get the selection name.
     *
     * @return string
     */
    public function getSelectName()
    {
        return 'selectedLibraryFiles';
    }
}
