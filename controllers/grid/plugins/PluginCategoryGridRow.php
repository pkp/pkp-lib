<?php

/**
 * @file controllers/grid/plugins/PluginCategoryGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginCategoryGridRow
 * @ingroup controllers_grid_plugins
 *
 * @brief Plugin category grid row definition.
 */

namespace PKP\controllers\grid\plugins;

use PKP\controllers\grid\GridCategoryRow;

class PluginCategoryGridRow extends GridCategoryRow
{
    //
    // Overridden methods from GridCategoryRow
    //
    /**
     * @copydoc GridCategoryRow::getCategoryLabel()
     */
    public function getCategoryLabel()
    {
        $pluginCategory = $this->getData();
        return __("plugins.categories.{$pluginCategory}");
    }
}
