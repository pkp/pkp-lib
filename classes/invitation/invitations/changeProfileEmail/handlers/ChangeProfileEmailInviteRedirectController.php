<?php

/**
 * @file classes/invitation/invitations/changeProfileEmail/handlers/ChangeProfileEmailInviteRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInviteRedirectController
 *
 */

namespace PKP\invitation\invitations\changeProfileEmail\handlers;

use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\changeProfileEmail\ChangeProfileEmailInvite;

class ChangeProfileEmailInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): ChangeProfileEmailInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            $request->getDispatcher()->handle404();
        }

        $user = Repo::user()->get($this->invitation->getUserId());

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
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            $request->getDispatcher()->handle404();
        }

        $user = Repo::user()->get($this->invitation->getUserId());

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
            $this->getInvitation()->finalize();
        } elseif ($action == InvitationAction::DECLINE) {
            $this->getInvitation()->decline();
        }
    }
}
