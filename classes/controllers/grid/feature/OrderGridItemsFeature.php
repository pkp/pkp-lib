<?php

/**
 * @file classes/controllers/grid/feature/OrderGridItemsFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrderGridItemsFeature
 *
 * @ingroup controllers_grid_feature
 *
 * @brief Implements grid ordering functionality.
 *
 */

namespace PKP\controllers\grid\feature;

class OrderGridItemsFeature extends OrderItemsFeature
{
    /**
     * Constructor.
     *
     * @copydoc OrderItemsFeature::OrderItemsFeature()
     *
     * @param null|mixed $nonOrderableItemsMessage
     */
    public function __construct($overrideRowTemplate = true, $nonOrderableItemsMessage = null)
    {
        parent::__construct($overrideRowTemplate, $nonOrderableItemsMessage);
    }


    //
    // Extended methods from GridFeature.
    //
    /**
     * @see GridFeature::getJSClass()
     */
    public function getJSClass()
    {
        return '$.pkp.classes.features.OrderGridItemsFeature';
    }


    //
    // Hooks implementation.
    //
    /**
     * @see GridFeature::saveSequence()
     *
     * @param array $args
     */
    public function saveSequence($args)
    {
        $request = & $args['request'];
        $grid = & $args['grid'];

        $data = json_decode($request->getUserVar('data'));

        $gridElements = $grid->getGridDataElements($request);
        if (empty($gridElements)) {
            return;
        }
        $firstSeqValue = $grid->getDataElementSequence(reset($gridElements));
        foreach ($gridElements as $rowId => $element) {
            $rowPosition = array_search($rowId, $data);
            $newSequence = $firstSeqValue + $rowPosition;
            $currentSequence = $grid->getDataElementSequence($element);
            if ($newSequence != $currentSequence) {
                $grid->setDataElementSequence($request, $rowId, $element, $newSequence);
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\OrderGridItemsFeature', '\OrderGridItemsFeature');
}
