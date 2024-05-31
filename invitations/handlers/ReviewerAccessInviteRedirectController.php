<?php

/**
 * @file invitations/handlers/ReviewerAccessInviteRedirectController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteRedirectController
 *
 * @ingroup invitations\handlers
 *
 * @brief Change Profile Email invitation
 */

namespace PKP\invitations\handlers;

use APP\core\Request;
use APP\facades\Repo;
use Exception;
use PKP\core\PKPApplication;
use PKP\invitation\invitations\enums\InvitationAction;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\PKPInvitationActionRedirectController;
use PKP\invitations\ReviewerAccessInvite;

class ReviewerAccessInviteRedirectController extends PKPInvitationActionRedirectController
{
    public function getInvitation(): ReviewerAccessInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void 
    {
        if ($this->invitation->getStatus() !== InvitationStatus::ACCEPTED) {
            $request->getDispatcher()->handle404();
        }
        
        $context = $request->getContext();

        $reviewAssignment = Repo::reviewAssignment()->get($this->getInvitation()->reviewAssignmentId);

        if (!$reviewAssignment) {
            throw new Exception();
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

        $request->redirectUrl($url);
    }
    
    public function declineHandle(Request $request): void 
    {
        if ($this->invitation->getStatus() !== InvitationStatus::DECLINED) {
            $request->getDispatcher()->handle404();
        }

        $context = $request->getContext();

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
