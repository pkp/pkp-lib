<?php

/**
 * @file controllers/grid/settings/reviewForms/ReviewFormElementGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementGridRow
 *
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief ReviewFormElements grid row definition
 */

namespace PKP\controllers\grid\settings\reviewForms;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class ReviewFormElementGridRow extends GridRow
{
    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);
        // add grid row actions: edit, delete

        $element = parent::getData();
        assert($element instanceof \PKP\reviewForm\ReviewFormElement);
        $rowId = $this->getId();

        $router = $request->getRouter();
        if (!empty($rowId) && is_numeric($rowId)) {
            // add 'edit' grid row action
            $this->addAction(
                new LinkAction(
                    'edit',
                    new AjaxModal(
                        $router->url($request, null, null, 'editReviewFormElement', null, ['rowId' => $rowId, 'reviewFormId' => $element->getReviewFormId()]),
                        __('grid.action.edit'),
                        'modal_edit',
                        true
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );
            // add 'delete' grid row action
            $this->addAction(
                new LinkAction(
                    'delete',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('manager.reviewFormElements.confirmDelete'),
                        null,
                        $router->url($request, null, null, 'deleteReviewFormElement', null, ['rowId' => $rowId, 'reviewFormId' => $element->getReviewFormId()])
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }
}
