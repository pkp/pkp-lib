<?php

/**
 * @file invitations/handlers/ChangeProfileEmailInviteRedirectController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInviteRedirectController
 *
 * @ingroup invitations\handlers
 *
 * @brief Change Profile Email invitation
 */

namespace PKP\invitations\handlers;

use APP\core\Request;
use APP\notification\NotificationManager;
use PKP\core\PKPApplication;
use APP\facades\Repo;
use PKP\invitation\invitations\enums\InvitationAction;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\PKPInvitationActionRedirectController;
use PKP\invitations\ChangeProfileEmailInvite;

class ChangeProfileEmailInviteRedirectController extends PKPInvitationActionRedirectController
{
    public function getInvitation(): ChangeProfileEmailInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void 
    {
        if ($this->invitation->getStatus() !== InvitationStatus::ACCEPTED) {
            $request->getDispatcher()->handle404();
        }

        $user = Repo::user()->get($this->invitation->invitationModel->userId);

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($user->getId());

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null,
            'user',
            'profile',
            [
                'contact'
            ]
        );

        $request->redirectUrl($url);
    }
    
    public function declineHandle(Request $request): void 
    {
        if ($this->invitation->getStatus() !== InvitationStatus::DECLINED) {
            $request->getDispatcher()->handle404();
        }

        $user = Repo::user()->get($this->invitation->invitationModel->userId);

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($user->getId());

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null,
            'user',
            'profile',
            [
                'contact'
            ]
        );

        $request->redirectUrl($url);
    }

    public function preRedirectActions(InvitationAction $action) 
    {
        if ($action == InvitationAction::ACCEPT) {
            $this->getInvitation()->finalise();
        } elseif ($action == InvitationAction::DECLINE) {
            $this->getInvitation()->decline();
        }
    }
}
