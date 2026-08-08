<?php

/**
 * @file classes/invitation/invitations/handlers/reviewerAccess/ReviewerAccessInviteRedirectController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteRedirectController
 *
 */

namespace PKP\invitation\invitations\reviewerAccess\handlers;

use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\reviewerAccess\steps\ReviewerAccessInvitationSteps;
use PKP\user\User;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;

class ReviewerAccessInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): ReviewerAccessInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('invitation', $this->getInvitation());
        $context = $request->getContext();
        $userId = $this->getInvitation()->invitationModel->userId;
        $user = $userId ? Repo::user()->get($userId) : null;
        $steps = $this->getAcceptSteps($this->invitation, $context, $user);

        $templateMgr->setState([
            'steps' => $steps,
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
     * Redirect to login page after confirming the invitation decline
     *
     * @throws \Exception
     */
    public function confirmDecline(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException();
        }

        $context = $request->getContext();
        $payload = $this->getInvitation()->getPayload();
        $reviewAssignmentId = $payload->reviewAssignmentId;
        if (!$reviewAssignmentId) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId);
        if (!$reviewAssignment) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'reviewer',
            'submission',
            null,
            [
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'reviewId' => $reviewAssignment->getId(),
            ]
        );

        $this->getInvitation()->decline();
        $reviewAssignment->setDeclined(true);
        $reviewAssignment->setLastModified(Core::getCurrentDate());
        Repo::reviewAssignment()->edit($reviewAssignment, []);

        $request->redirectUrl($url);
    }

    public function preRedirectActions(InvitationAction $action): void
    {
        return;
    }

    public function getAcceptSteps(Invitation $invitation, Context $context, ?User $user): array
    {
        return (new ReviewerAccessInvitationSteps())->getAcceptSteps($invitation, $context, $user);
    }
}
