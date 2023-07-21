<?php

/**
 * @file invitation/invitations/ReviewerAccessInvite.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
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
use PKP\db\DAORegistry;
use PKP\invitation\invitations\BaseInvitation;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Validation;
use PKP\session\SessionManager;
use ReviewAssignment;

class ReviewerAccessInvite extends BaseInvitation
{
    private ReviewAssignment $reviewAssignment;

    /**
     * Create a new invitation instance.
     */
    public function __construct(
        public ?int $invitedUserId, 
        ?string $email, 
        int $contextId, 
        public int $reviewAssignmentId
    )
    {
        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->getById($contextId);

        $expiryDays = ($this->context->getData('numWeeksPerReview') + 4) * 7;

        parent::__construct($invitedUserId, $email, $contextId, $reviewAssignmentId, $expiryDays);

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $this->reviewAssignment = $reviewAssignmentDao->getById($reviewAssignmentId);
    }

    public function getInvitationMailable(): ?Mailable 
    {
        if (isset($this->mailable)) {
            $url = $this->getAcceptInvitationUrl();

            $this->mailable->buildViewDataUsing(function () use ($url) {
                return [
                    ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL => $url
                ];
            });
        }
        
        return $this->mailable;
    }
    
    /**
     * @return bool
     */
    public function preDispatchActions(): bool 
    {
        $hadCancels = Repo::invitation()
            ->cancelInvitationFamily($this->className, $this->email, $this->contextId, $this->reviewAssignmentId);

        return true;
    }

    public function invitationAcceptHandle() : void
    {
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

        if ($context->getData('reviewerAccessKeysEnabled')) {
            $this->_validateAccessKey();
        }

        parent::invitationAcceptHandle();

        $request->redirectUrl($url);
    }

    private function _validateAccessKey() : bool
    {
        $reviewAssignment = $this->reviewAssignment;
        $reviewId = $reviewAssignment->getId();

        // Check if the user is already logged in
        $sessionManager = SessionManager::getManager();
        $session = $sessionManager->getUserSession();
        if ($session->getUserId()) {
            return false;
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getById($reviewId);
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
