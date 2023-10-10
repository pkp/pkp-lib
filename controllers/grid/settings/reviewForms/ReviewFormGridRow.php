<?php

/**
 * @file controllers/grid/settings/reviewForms/ReviewFormGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormGridRow
 *
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief ReviewForm grid row definition
 */

namespace PKP\controllers\grid\settings\reviewForms;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class ReviewFormGridRow extends GridRow
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

        // Is this a new row or an existing row?
        $element = $this->getData();
        assert($element instanceof \PKP\reviewForm\ReviewForm);

        $rowId = $this->getId();

        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();

            // determine whether or not this Review Form is editable.
            $canEdit = ($element->getIncompleteCount() == 0 && $element->getCompleteCount() == 0);

            // if review form is editable, add 'edit' grid row action
            if ($canEdit) {
                $this->addAction(
                    new LinkAction(
                        'edit',
                        new AjaxModal(
                            $router->url($request, null, null, 'editReviewForm', null, ['rowId' => $rowId]),
                            __('grid.action.edit'),
                            'modal_edit',
                            true
                        ),
                        __('grid.action.edit'),
                        'edit'
                    )
                );
            }

            // if review form is not editable, add 'copy' grid row action
            $this->addAction(
                new LinkAction(
                    'copy',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('manager.reviewForms.confirmCopy'),
                        null,
                        $router->url($request, null, null, 'copyReviewForm', null, ['rowId' => $rowId])
                    ),
                    __('grid.action.copy'),
                    'copy'
                )
            );

            // add 'preview' grid row action
            $this->addAction(
                new LinkAction(
                    'preview',
                    new AjaxModal(
                        $router->url($request, null, null, 'editReviewForm', null, ['rowId' => $rowId, 'preview' => 1]),
                        __('grid.action.preview'),
                        'preview',
                        true
                    ),
                    __('grid.action.preview'),
                    'preview'
                )
            );

            // if review form is editable, add 'delete' grid row action.
            if ($canEdit) {
                $this->addAction(
                    new LinkAction(
                        'delete',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('manager.reviewForms.confirmDelete'),
                            null,
                            $router->url($request, null, null, 'deleteReviewForm', null, ['rowId' => $rowId])
                        ),
                        __('grid.action.delete'),
                        'delete'
                    )
                );
            }
        }
    }
}
