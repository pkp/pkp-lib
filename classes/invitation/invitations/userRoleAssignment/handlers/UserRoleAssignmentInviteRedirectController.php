<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/handlers/UserRoleAssignmentInviteRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInviteRedirectController
 *
 */

namespace PKP\invitation\invitations\userRoleAssignment\handlers;

use APP\core\Request;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\invitation\stepTypes\AcceptInvitationStep;
use APP\facades\Repo;

class UserRoleAssignmentInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): UserRoleAssignmentInvite
    {
        return $this->invitation;
    }

    /**
     * Redirect to accept invitation page
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function acceptHandle(Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('invitation', $this->invitation);
        $context = $request->getContext();
        $steps = new AcceptInvitationStep();
        $invitationModel = $this->invitation->invitationModel->toArray();
        $user = $invitationModel['userId'] ?Repo::user()->get($invitationModel['userId']) : null;
        $templateMgr->setState([
            'steps' => $steps->getSteps($this->invitation,$context,$user),
            'primaryLocale' => $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'invitationId' => (int)$request->getUserVar('id') ?: null,
            'invitationKey' => $request->getUserVar('key') ?: null,
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
        ]);
        $templateMgr->display('invitation/acceptInvitation.tpl');
    }

    /**
     * Redirect to login page after decline invitation
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function declineHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            $request->getDispatcher()->handle404('The link is deactivated as the invitation was cancelled');
        }

        $context = $request->getContext();

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'login',
            null,
            null,
            [
            ]
        );

        $this->getInvitation()->decline();

        $request->redirectUrl($url);
    }

    public function preRedirectActions(InvitationAction $action)
    {
        return;
    }
}
