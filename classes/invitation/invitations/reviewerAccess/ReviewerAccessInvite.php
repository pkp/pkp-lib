<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/ReviewerAccessInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvite
 *
 * @brief Reviewer with Access Key invitation
 */

namespace PKP\invitation\invitations\reviewerAccess;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\invitation\core\contracts\IBackofficeHandleable;
use PKP\invitation\core\contracts\IMailableUrlUpdateable;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\reviewerAccess\handlers\ReviewerAccessInviteRedirectController;
use PKP\invitation\invitations\reviewerAccess\payload\ReviewerAccessInvitePayload;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Validation;

class ReviewerAccessInvite extends Invitation implements IBackofficeHandleable, IMailableUrlUpdateable
{
    use ShouldValidate;

    public const INVITATION_TYPE = 'reviewerAccess';

    protected array $notAccessibleAfterInvite = [
        'reviewAssignmentId',
    ];

    /**
     * @inheritDoc
     */

    public static function getType(): string
    {
        return self::INVITATION_TYPE;
    }

    /**
     * @inheritDoc
     */
    protected function getPayloadClass(): string
    {
        return ReviewerAccessInvitePayload::class;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): ReviewerAccessInvitePayload
    {
        return parent::getPayload();
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

    public function getNotAccessibleAfterInvite(): array
    {
        return array_merge(parent::getNotAccessibleAfterInvite(), $this->notAccessibleAfterInvite);
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

    public function finalize(): void
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
        $reviewAssignment = Repo::reviewAssignment()->get($this->getPayload()->reviewAssignmentId);

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

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new ReviewerAccessInviteRedirectController($this);
    }

    /**
     * @inheritDoc
     */
    public function getValidationRules(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array 
    {
        return [
            'reviewAssignmentId' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $reviewAssignment = Repo::reviewAssignment()->get($value);

                    if (!$reviewAssignment) {
                        $fail(__('invitation.reviewerAccess.validation.error.reviewAssignmentId.notExisting', 
                            [
                                'reviewAssignmentId' => $value
                            ])
                        );
                    }
                }
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        return [];
    }
}
