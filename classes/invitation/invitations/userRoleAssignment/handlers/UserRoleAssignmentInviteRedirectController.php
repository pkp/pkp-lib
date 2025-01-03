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
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\invitation\stepTypes\AcceptInvitationStep;

/**
 * @extends InvitationActionRedirectController<UserRoleAssignmentInvite>
 */
class UserRoleAssignmentInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): UserRoleAssignmentInvite
    {
        return $this->invitation;
    }

    /**
     * Redirect to accept invitation page
     *
     * @throws \Exception
     */
    public function acceptHandle(Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $this->getInvitation()->changeInvitationUserIdUsingUserEmail();

        $templateMgr->assign('invitation', $this->getInvitation());
        $context = $request->getContext();
        $steps = new AcceptInvitationStep();
        $invitationModel = $this->getInvitation()->invitationModel->toArray();
        $user = $invitationModel['userId'] ? Repo::user()->get($invitationModel['userId']) : null;
        $templateMgr->setState([
            'steps' => $steps->getSteps($this->getInvitation(), $context, $user),
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
     *
     * @throws \Exception
     */
    public function declineHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException();
        }

        $this->getInvitation()->changeInvitationUserIdUsingUserEmail();

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

    public function preRedirectActions(InvitationAction $action): void
    {
        return;
    }
}
