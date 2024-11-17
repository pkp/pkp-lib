<?php
/**
 * @file controllers/grid/settings/reviewForms/ReviewFormGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormGridCellProvider
 *
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Subclass for review form column's cell provider
 */

namespace PKP\controllers\grid\settings\reviewForms;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\reviewForm\ReviewForm;

class ReviewFormGridCellProvider extends GridCellProvider
{
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof ReviewForm && !empty($columnId));
        switch ($columnId) {
            case 'name':
                return ['label' => $element->getLocalizedTitle()];
            case 'inReview':
                return ['label' => $element->getIncompleteCount()];
            case 'completed':
                return ['label' => $element->getCompleteCount()];
            case 'active':
                return ['selected' => $element->getActive()];
        }
        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * @see GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        switch ($column->getId()) {
            case 'active':
                /** @var ReviewForm */
                $element = $row->getData();

                $router = $request->getRouter();

                if ($element->getActive()) {
                    return [new LinkAction(
                        'deactivateReviewForm',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('manager.reviewForms.confirmDeactivate'),
                            null,
                            $router->url(
                                $request,
                                null,
                                'grid.settings.reviewForms.ReviewFormGridHandler',
                                'deactivateReviewForm',
                                null,
                                ['reviewFormKey' => $element->getId()]
                            ),
                            'negative'
                        )
                    )];
                } else {
                    return [new LinkAction(
                        'activateReviewForm',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('manager.reviewForms.confirmActivate'),
                            null,
                            $router->url(
                                $request,
                                null,
                                'grid.settings.reviewForms.ReviewFormGridHandler',
                                'activateReviewForm',
                                null,
                                ['reviewFormKey' => $element->getId()]
                            ),
                            'primary'
                        )
                    )];
                }
        }
        return parent::getCellActions($request, $row, $column, $position);
    }
}
