<?php

/**
 * @file controllers/grid/eventLog/linkAction/EmailLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailLinkAction
 * @ingroup controllers_api_submission
 *
 * @brief An action to open up a modal to view an email sent to a user.
 */

namespace PKP\controllers\grid\eventLog\linkAction;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class EmailLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param string $modalTitle Title of the modal
     * @param array $actionArgs The action arguments.
     */
    public function __construct($request, $modalTitle, $actionArgs)
    {
        $router = $request->getRouter();

        // Instantiate the view email modal.
        $ajaxModal = new AjaxModal(
            $router->url($request, null, null, 'viewEmail', null, $actionArgs),
            $modalTitle,
            'modal_email'
        );

        // Configure the link action.
        parent::__construct(
            'viewEmail',
            $ajaxModal,
            $modalTitle,
            'notify'
        );
    }
}
