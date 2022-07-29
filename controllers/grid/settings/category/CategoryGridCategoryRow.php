<?php

/**
 * @file controllers/grid/settings/category/CategoryGridCategoryRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridCategoryRow
 * @ingroup controllers_grid_settings_category
 *
 * @brief Category grid category row definition
 */

namespace PKP\controllers\grid\settings\category;

use APP\facades\Repo;
use PKP\controllers\grid\GridCategoryRow;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class CategoryGridCategoryRow extends GridCategoryRow
{
    //
    // Overridden methods from GridCategoryRow
    //
    /**
     * @copydoc GridCategoryRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        // Do the default initialization
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $categoryId = $this->getId();
        if (!empty($categoryId) && is_numeric($categoryId)) {
            // Only add row actions if this is an existing row
            $category = $this->getData();
            $router = $request->getRouter();

            $childCategoryCount = Repo::category()->getCount(
                Repo::category()->getCollector()
                    ->filterByParentIds([$categoryId])
            );
            if ($childCategoryCount == 0) {
                $this->addAction(
                    new LinkAction(
                        'deleteCategory',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('common.confirmDelete'),
                            __('common.delete'),
                            $router->url($request, null, null, 'deleteCategory', null, ['categoryId' => $categoryId]),
                            'modal_delete'
                        ),
                        __('grid.action.remove'),
                        'delete'
                    )
                );
            }

            $this->addAction(new LinkAction(
                'editCategory',
                new AjaxModal(
                    $router->url($request, null, null, 'editCategory', null, ['categoryId' => $categoryId]),
                    __('grid.category.edit'),
                    'modal_edit'
                ),
                $category->getLocalizedTitle()
            ), GridRow::GRID_ACTION_POSITION_ROW_CLICK);
        }
    }

    /**
     * Category rows only have one cell and one label.  This is it.
     * return string
     */
    public function getCategoryLabel()
    {
        return '';
    }
}
