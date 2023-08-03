<?php

/**
 * @file pages/invitation/PKPInvitationHandler.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationStatus;

class PKPInvitationHandler extends Handler
{
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * Accept invitation handler
     */
    public function accept($args, $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitation->acceptHandle();
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitation->declineHandle();
    }

    private function getInvitationByKey(Request $request): BaseInvitation
    {
        $key = $request->getUserVar('key')
            ? $request->getUserVar('key')
            : null;

        $invitation = Repo::invitation()->getByKey($key);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404();
        }

        if ($invitation->isExpired()) {
            $invitation->markStatus(InvitationStatus::EXPIRED);
            $request->getDispatcher()->handle404();
        }

        return $invitation;
    }
}
