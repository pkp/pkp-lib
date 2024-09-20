<?php

/**
 * @file classes/invitation/invitations/registrationAccess/handlers/RegistrationAccessInviteRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationAccessInviteRedirectController
 *
 */

namespace PKP\invitation\invitations\registrationAccess\handlers;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Exception;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\registrationAccess\RegistrationAccessInvite;

class RegistrationAccessInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): RegistrationAccessInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            $request->getDispatcher()->handle404();
        }

        $user = Repo::user()->get($this->invitation->getUserId(), true);

        if (!$user) {
            throw new Exception();
        }

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null,
            'user',
            'activateUser',
            [
                $user->getUsername(),
            ]
        );

        $contextId = $this->invitation->getContextId();
        if (isset($contextId)) {
            $context = Application::getContextDAO()->getById($contextId);

            $url = PKPApplication::get()->getDispatcher()->url(
                PKPApplication::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                $context->getData('urlPath'),
                'user',
                'activateUser',
                [
                    $user->getUsername(),
                ]
            );
        }

        $request->redirectUrl($url);
    }

    public function declineHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            $request->getDispatcher()->handle404();
        }

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null,
            'user',
            'login',
            [
            ]
        );

        $contextId = $this->invitation->getContextId();
        if (isset($contextId)) {
            $context = Application::getContextDAO()->getById($contextId);

            $url = PKPApplication::get()->getDispatcher()->url(
                PKPApplication::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                $context->getData('urlPath'),
                'user',
                'login',
                null,
                [
                ]
            );
        }

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
