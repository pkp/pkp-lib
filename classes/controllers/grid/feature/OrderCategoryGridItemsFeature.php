<?php

/**
 * @file classes/controllers/grid/feature/OrderCategoryGridItemsFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrderCategoryGridItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Implements category grid ordering functionality.
 *
 */

namespace PKP\controllers\grid\feature;

class OrderCategoryGridItemsFeature extends OrderItemsFeature
{
    public const ORDER_CATEGORY_GRID_CATEGORIES_ONLY = 1;
    public const ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY = 2;
    public const ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS = 3;

    /**
     * Constructor.
     *
     * @param int $typeOption Defines which grid elements will
     * be orderable (categories and/or rows).
     * @param bool $overrideRowTemplate This feature uses row
     * actions and it will force the usage of the gridRow.tpl.
     * If you want to use a different grid row template file, set this flag to
     * false and make sure to use a template file that adds row actions.
     * @param GridHandler $grid The grid this feature is to be part of
     */
    public function __construct($typeOption = self::ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS, $overrideRowTemplate = true, $grid = null)
    {
        parent::__construct($overrideRowTemplate);

        if ($grid) {
            $grid->_constants['ORDER_CATEGORY_GRID_CATEGORIES_ONLY'] = self::ORDER_CATEGORY_GRID_CATEGORIES_ONLY;
            $grid->_constants['ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY'] = self::ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY;
            $grid->_constants['ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS'] = self::ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS;
        }

        $this->addOptions(['type' => $typeOption]);
    }


    //
    // Getters and setters.
    //
    /**
     * Return this feature type.
     *
     * @return int One of the ORDER_CATEGORY_GRID_... constants
     */
    public function getType()
    {
        $options = $this->getOptions();
        return $options['type'];
    }


    //
    // Extended methods from GridFeature.
    //
    /**
     * @see GridFeature::getJSClass()
     */
    public function getJSClass()
    {
        return '$.pkp.classes.features.OrderCategoryGridItemsFeature';
    }


    //
    // Hooks implementation.
    //
    /**
     * @see OrderItemsFeature::getInitializedRowInstance()
     */
    public function getInitializedRowInstance($args)
    {
        if ($this->getType() != self::ORDER_CATEGORY_GRID_CATEGORIES_ONLY) {
            parent::getInitializedRowInstance($args);
        }
    }

    /**
     * @see GridFeature::getInitializedCategoryRowInstance()
     */
    public function getInitializedCategoryRowInstance($args)
    {
        if ($this->getType() != self::ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY) {
            $row = & $args['row'];
            $this->addRowOrderAction($row);
        }
    }

    /**
     * @see GridFeature::saveSequence()
     */
    public function saveSequence($args)
    {
        $request = & $args['request'];
        $grid = & $args['grid'];

        $data = json_decode($request->getUserVar('data'));
        $gridCategoryElements = $grid->getGridDataElements($request);

        if ($this->getType() != self::ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY) {
            $categoriesData = [];
            foreach ($data as $categoryData) {
                $categoriesData[] = $categoryData->categoryId;
            }

            // Save categories sequence.
            $firstSeqValue = $grid->getDataElementSequence(reset($gridCategoryElements));
            foreach ($gridCategoryElements as $rowId => $element) {
                $rowPosition = array_search($rowId, $categoriesData);
                $newSequence = $firstSeqValue + $rowPosition;
                $currentSequence = $grid->getDataElementSequence($element);
                if ($newSequence != $currentSequence) {
                    $grid->setDataElementSequence($request, $rowId, $element, $newSequence);
                }
            }
        }

        // Save rows sequence, if this grid has also orderable rows inside each category.
        $this->_saveRowsInCategoriesSequence($request, $grid, $gridCategoryElements, $data);
    }


    //
    // Private helper methods.
    //
    /**
     * Save row elements sequence inside categories.
     *
     * @param PKPRequest $request
     * @param GridHandler $grid
     * @param array $gridCategoryElements
     */
    public function _saveRowsInCategoriesSequence($request, &$grid, $gridCategoryElements, $data)
    {
        if ($this->getType() != self::ORDER_CATEGORY_GRID_CATEGORIES_ONLY) {
            foreach ($gridCategoryElements as $categoryId => $element) {
                $gridRowElements = $grid->getGridCategoryDataElements($request, $element);
                if (!$gridRowElements) {
                    continue;
                }

                // Get the correct rows sequence data.
                /** @var ?array */
                $rowsData = null;
                foreach ($data as $categoryData) {
                    if ($categoryData->categoryId == $categoryId) {
                        $rowsData = $categoryData->rowsId;
                        break;
                    }
                }

                unset($rowsData[0]); // remove the first element, it is always the parent category ID
                $gridRowElement = reset($gridRowElements);
                $firstSeqValue = $grid->getDataElementInCategorySequence($categoryId, $gridRowElement);
                foreach ($gridRowElements as $rowId => $element) {
                    $newSequence = array_search($rowId, $rowsData);
                    $currentSequence = $grid->getDataElementInCategorySequence($categoryId, $element);
                    if ($newSequence != $currentSequence) {
                        $grid->setDataElementInCategorySequence($categoryId, $element, $newSequence);
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\OrderCategoryGridItemsFeature', '\OrderCategoryGridItemsFeature');
    foreach ([
        'ORDER_CATEGORY_GRID_CATEGORIES_ONLY',
        'ORDER_CATEGORY_GRID_CATEGORIES_ROWS_ONLY',
        'ORDER_CATEGORY_GRID_CATEGORIES_AND_ROWS',
    ] as $constantName) {
        define($constantName, constant('\OrderCategoryGridItemsFeature::' . $constantName));
    }
}
