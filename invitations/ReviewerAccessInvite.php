<?php

/**
 * @file invitations/ReviewerAccessInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvite
 *
 * @brief Reviewer with Access Key invitation
 */

namespace PKP\invitations;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\invitation\invitations\enums\InvitationAction;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\contracts\IBackofficeHandleable;
use PKP\invitation\invitations\contracts\IMailableUrlUpdateable;
use PKP\invitation\invitations\Invitation;
use PKP\invitation\invitations\PKPInvitationActionRedirectController;
use PKP\invitation\invitations\traits\ShouldValidate;
use PKP\invitation\models\InvitationModel;
use PKP\invitations\handlers\ReviewerAccessInviteRedirectController;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Validation;

class ReviewerAccessInvite extends Invitation implements IBackofficeHandleable, IMailableUrlUpdateable
{
    use ShouldValidate;
    
    const INVITATION_TYPE = 'reviewerAccess';

    public ?int $reviewAssignmentId = null;

    public static function getType(): string 
    {
        return self::INVITATION_TYPE;
    }

    protected function getExpiryDays(): int 
    {
        if (!isset($this->invitationModel) || !isset($this->invitationModel->contextId)) {
            throw new Exception('The context id is nessesary');
        }

        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);

        if (!isset($context)) {
            throw new Exception('The context is nessesary');
        }

        return ($context->getData('numWeeksPerReview') + 4) * 7;
    }

    public function getHiddenAfterDispatch(): array
    {
        $baseHiddenItems = parent::getHiddenAfterDispatch();
        
        $additionalHiddenItems = ['reviewAssignmentId'];

        return array_merge($baseHiddenItems, $additionalHiddenItems);
    }

    public function updateMailableWithUrl(Mailable $mailable): void 
    {
        $url = $this->getActionURL(InvitationAction::ACCEPT);

        $mailable->buildViewDataUsing(function () use ($url) {
            return [
                ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL => $url
            ];
        });
    }

    public function preDispatchActions(): void
    {
        if (!isset($this->reviewAssignmentId)) {
            throw new Exception('The review assignment id should be declared before dispatch');
        }

        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);

        if (!$reviewAssignment) {
            throw new Exception('The review assignment ID does not correspond to a valid assignment');
        }

        $pendingInvitations = InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->byContextId($this->invitationModel->contextId)
            ->byUserId($this->invitationModel->userId)
            ->get();

        foreach($pendingInvitations as $pendingInvitation) {
            $pendingInvitation->markAs(InvitationStatus::CANCELLED);
        }
    }

    public function finalise(): void
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);

        if ($context->getData('reviewerAccessKeysEnabled')) {
            if (!$this->_validateAccessKey()) {
                throw new Exception();
            }

            $this->invitationModel->markAs(InvitationStatus::ACCEPTED);
        }
    }

    private function _validateAccessKey(): bool
    {
        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);

        if (!$reviewAssignment) {
            return false;
        }

        // Check if the user is already logged in
        if (Application::get()->getRequest()->getSessionGuard()->getUserId() && Application::get()->getRequest()->getSessionGuard()->getUserId() != $this->invitationModel->userId) {
            return false;
        }

        $reviewSubmission = Repo::submission()->getByBestId($reviewAssignment->getSubmissionId());
        if (!isset($reviewSubmission)) {
            return false;
        }

        // Get the reviewer user object
        $user = Repo::user()->get($this->invitationModel->userId);
        if (!$user) {
            return false;
        }

        // Register the user object in the session
        $reason = null;
        Validation::registerUserSession($user, $reason);

        return true;
    }

    public function getInvitationActionRedirectController(): ?PKPInvitationActionRedirectController
    {
        return new ReviewerAccessInviteRedirectController($this);
    }

    public function validate(): bool 
    {
        if (isset($this->reviewAssignmentId)) {
            $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);

            if (!$reviewAssignment) {
                $this->addError('The review assignment ID does not correspond to a valid assignment');
            }
        }

        return $this->isValid();
    }
}
