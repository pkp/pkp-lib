<?php

/**
 * @file classes/controllers/grid/feature/OrderListbuilderItemsFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrderListbuilderItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Implements listbuilder ordering functionality.
 *
 */

namespace PKP\controllers\grid\feature;

class OrderListbuilderItemsFeature extends OrderItemsFeature
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(false);
    }


    //
    // Extended methods from GridFeature.
    //
    /**
     * @see GridFeature::getJSClass()
     */
    public function getJSClass()
    {
        return '$.pkp.classes.features.OrderListbuilderItemsFeature';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\OrderListbuilderItemsFeature', '\OrderListbuilderItemsFeature');
}
