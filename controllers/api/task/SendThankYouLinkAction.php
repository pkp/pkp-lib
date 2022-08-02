<?php

/**
 * @file controllers/api/task/SendThankYouLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendThankYouLinkAction
 * @ingroup controllers_api_task
 *
 * @brief An action to open up a modal to send a thank you email to users assigned to a review task.
 */

namespace PKP\controllers\api\task;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class SendThankYouLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param array $actionArgs The action arguments.
     */
    public function __construct($request, $modalTitle, $actionArgs)
    {
        // Instantiate the send thank you modal.
        $router = $request->getRouter();

        $ajaxModal = new AjaxModal(
            $router->url($request, null, null, 'editThankReviewer', null, $actionArgs),
            __($modalTitle),
            'modal_email'
        );

        // Configure the link action.
        parent::__construct(
            'thankReviewer',
            $ajaxModal,
            __('editor.review.thankReviewer'),
            'accepted'
        );
    }
}
