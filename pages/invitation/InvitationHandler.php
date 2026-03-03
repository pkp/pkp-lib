<?php

/**
 * @file pages/invitation/InvitationHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Illuminate\Http\Response;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvitationHandler extends Handler
{
    public $_isBackendPage = true;
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * Accept invitation handler
     */
    public function accept(array $args, Request $request): void
    {
        $this->setupTemplate($request);
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::ACCEPT);
        $invitationHandler->acceptHandle($request);
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $this->setupTemplate($request);
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->declineHandle($request);
    }

    /**
     * Confirm decline invitation handler
     * 
     * This force a POST request to confrim the invitation decline from user
     * @see https://github.com/pkp/pkp-lib/issues/11690 and https://github.com/pkp/pkp-lib/issues/12332
     */
    public function confirmDecline(array $args, Request $request): void
    {
        if (!$request->isPost()) {
            throw new MethodNotAllowedHttpException(['POST']);
        }

        if (!$request->checkCSRF()) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }

        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::DECLINE);
        $invitationHandler->confirmDecline($request);
    }

    private function getInvitationByKey(Request $request): Invitation
    {
        $key = $request->getUserVar('key') ?: null;
        $id = $request->getUserVar('id') ?: null;

        $invitation = Repo::invitation()
            ->getByIdAndKey($id, $key);

        if (!is_null($invitation)) {
            return $invitation;
        }

        // Check if invitation exists but is no longer actionable (already used or expired).
        // If so, display a friendly landing page instead of a 404 to avoid confusion
        if ($id && $key) {
            $expiredInvitation = Repo::invitation()->getById($id)?->invitationModel;

            if ($expiredInvitation && password_verify($key, $expiredInvitation->keyHash)) {
                $this->displayInvitationNotAvailablePage($request);
            }
        }

        throw new NotFoundHttpException();
    }

    public static function getActionUrl(InvitationAction $action, Invitation $invitation): ?string
    {
        $invitationId = $invitation->getId();
        $invitationKey = $invitation->getKey();

        if (!isset($invitationId) || !isset($invitationKey)) {
            return null;
        }

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : null;

        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $contextPath,
                static::REPLY_PAGE,
                $action->value,
                null,
                [
                    'id' => $invitationId,
                    'key' => $invitationKey,
                ]
            );
    }

    /**
     * Display a friendly landing page when an invitation is no longer actionable.
     */
    private function displayInvitationNotAvailablePage(Request $request): never
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $context = $request->getContext();
        $contextPath = $context?->getData('urlPath');

        $loginUrl = Application::get()->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $contextPath,
            'login',
        );

        $registerUrl = Application::get()->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $contextPath,
            'user',
            'register',
        );

        $templateMgr->assign([
            'loginUrl' => $loginUrl,
            'registerUrl' => $registerUrl,
            'pageComponent' => 'Page',
        ]);

        $templateMgr->display('invitation/invitationUnavailable.tpl');
        exit;
    }
}
