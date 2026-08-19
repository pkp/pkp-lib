<?php

/**
 * @file controllers/grid/eventLog/linkAction/ReviewChangeLinkAction.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewChangeLinkAction
 *
 * @ingroup controllers_grid_eventLog
 *
 * @brief An action to open up a modal showing the full detail (previous and new values) of a change made to a review.
 */

namespace PKP\controllers\grid\eventLog\linkAction;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class ReviewChangeLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param string $label Text shown on the grid button (e.g. "Show more")
     * @param string $modalTitle Title of the modal
     * @param array $actionArgs The action arguments, including submissionId and logEntryId
     */
    public function __construct($request, $label, $modalTitle, $actionArgs)
    {
        $router = $request->getRouter();
        $ajaxModal = new AjaxModal(
            $router->url(
                $request,
                null,
                'grid.eventLog.SubmissionReviewEventLogGridHandler',
                'viewReviewChange',
                null,
                $actionArgs
            ),
            $modalTitle,
        );

        // Configure the link action.
        parent::__construct(
            'viewReviewChange-' . $actionArgs['logEntryId'],
            $ajaxModal,
            $label,
            'notify'
        );
    }
}
