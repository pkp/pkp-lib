<?php
/**
 * @file controllers/grid/users/stageParticipant/linkAction/NotifyLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotifyLinkAction
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief An action to open up the notify part of the stage participants grid.
 */

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class NotifyLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param Submission $submission The submission
     * @param int $stageId
     * @param int $userId optional
     *  to show information about.
     */
    public function __construct($request, &$submission, $stageId, $userId = null)
    {
        // Prepare request arguments
        $requestArgs['submissionId'] = $submission->getId();
        $requestArgs['stageId'] = $stageId;
        if ($userId) {
            $requestArgs['userId'] = $userId;
        }

        $router = $request->getRouter();
        $ajaxModal = new AjaxModal(
            $router->url(
                $request,
                null,
                'grid.users.stageParticipant.StageParticipantGridHandler',
                'viewNotify',
                null,
                $requestArgs
            ),
            __('submission.stageParticipants.notify'),
            'modal_email'
        );

        // Configure the file link action.
        parent::__construct(
            'notify',
            $ajaxModal,
            __('submission.stageParticipants.notify'),
            'notify'
        );
    }
}
