<?php

/**
 * @file invitation/invitations/ReviewerAccessInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvite
 *
 * @ingroup invitations
 *
 * @brief Reviewer with Access Key invitation
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use PKP\core\PKPApplication;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Validation;
use PKP\session\SessionManager;
use ReviewAssignment;

class ReviewerAccessInvite extends BaseInvitation
{
    private ReviewAssignment $reviewAssignment;
    private bool $isValidated = false;

    /**
     * Create a new invitation instance.
     */
    public function __construct(
        public ?int $invitedUserId,
        int $contextId,
        public int $reviewAssignmentId
    ) {
        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->getById($contextId);

        $expiryDays = ($this->context->getData('numWeeksPerReview') + 4) * 7;

        parent::__construct($invitedUserId, null, $contextId, $reviewAssignmentId, $expiryDays);

        $this->reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);
    }

    public function getMailable(): ?Mailable
    {
        if (isset($this->mailable)) {
            $url = $this->getAcceptUrl();

            $this->mailable->buildViewDataUsing(function () use ($url) {
                return [
                    ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL => $url
                ];
            });
        }

        return $this->mailable;
    }

    /**
     */
    public function preDispatchActions(): bool
    {
        Invitation::byStatus(InvitationStatus::PENDING)
            ->byClassName($this->className)
            ->byContextId($this->contextId)
            ->byUserId($this->invitedUserId)
            ->markAs(InvitationStatus::CANCELLED);

        return true;
    }

    /**
     * Fill the Invitation Object with all the neccesary info in order to 
     * continue with the accept handle
     */
    public function finaliseAccept(): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if ($context->getData('reviewerAccessKeysEnabled')) {
            $this->isValidated = $this->_validateAccessKey();

            if ($this->isValidated) {
                parent::finaliseAccept();
            }
        }
    }

    public function acceptHandle(): void
    {
        $this->finaliseAccept();

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $reviewAssignment = $this->reviewAssignment;
        
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

    public function declineHandle(): void
    {
        $this->finaliseDecline();
    }

    private function _validateAccessKey(): bool
    {
        $reviewAssignment = $this->reviewAssignment;
        $reviewId = $reviewAssignment->getId();

        // Check if the user is already logged in
        $sessionManager = SessionManager::getManager();
        $session = $sessionManager->getUserSession();
        if ($session->getUserId() && $session->getUserId() != $this->userId) {
            return false;
        }

        $reviewAssignment = Repo::reviewAssignment()->get($reviewId);
        if (!$reviewAssignment) {
            return false;
        } // e.g. deleted review assignment

        $reviewSubmission = Repo::submission()->getByBestId($reviewAssignment->getSubmissionId());
        if (!isset($reviewSubmission)) {
            return false;
        }

        // Get the reviewer user object
        $user = Repo::user()->get($this->invitedUserId);
        if (!$user) {
            return false;
        }

        // Register the user object in the session
        $reason = null;
        Validation::registerUserSession($user, $reason);

        return true;
    }
}
