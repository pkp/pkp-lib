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
use PKP\identity\Identity;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\InvitationUIActionRedirectController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\reviewerAccess\handlers\api\ReviewerAccessInviteCreateController;
use PKP\invitation\invitations\reviewerAccess\handlers\api\ReviewerAccessInviteReceiveController;
use PKP\invitation\invitations\reviewerAccess\handlers\ReviewerAccessInviteRedirectController;
use PKP\invitation\invitations\reviewerAccess\handlers\ReviewerAccessInviteUIController;
use PKP\invitation\invitations\reviewerAccess\payload\ReviewerAccessInvitePayload;
use PKP\invitation\invitations\userRoleAssignment\rules\EmailMustNotExistRule;
use PKP\invitation\invitations\userRoleAssignment\rules\NoUserGroupChangesRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UserMustExistRule;
use PKP\mail\mailables\ReviewerAccessInvitationNotify;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Role;
use PKP\security\Validation;
use Illuminate\Database\Eloquent\Builder;
use PKP\invitation\models\InvitationModel;
use PKP\invitation\core\enums\InvitationStatus;

class ReviewerAccessInvite extends Invitation implements IApiHandleable
{
    use HasMailable;
    use ShouldValidate;

    public const INVITATION_TYPE = 'reviewerAccess';

    protected array $notAccessibleAfterInvite = [
        'submissionId',
        'reviewRoundId',
    ];

    protected array $notAccessibleBeforeInvite = [
        'orcid',
        'username',
        'password'
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

    public function getNotAccessibleBeforeInvite(): array
    {
        return array_merge(parent::getNotAccessibleBeforeInvite(), $this->notAccessibleBeforeInvite);
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

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new ReviewerAccessInviteRedirectController($this);
    }

    public function getInvitationUIActionRedirectController(): ?InvitationUIActionRedirectController
    {
        return new ReviewerAccessInviteUIController($this);
    }

    public function getMailable(): Mailable
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);
        $locale = $context->getPrimaryLocale();

        // Define the Mailable
        $mailable = new ReviewerAccessInvitationNotify($context, $this);
        $mailable->setData($locale);

        // Set the email send data
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

        if (!isset($emailTemplate)) {
            throw new \Exception('No email template found for key ' . $mailable::getEmailTemplateKey());
        }

        $inviter = $this->getInviter();

        $reciever = $this->getMailableReceiver($locale);

        $mailable
            ->sender($inviter)
            ->recipients([$reciever])
            ->subject($emailTemplate->getLocalizedData('subject', $locale))
            ->body($emailTemplate->getLocalizedData('body', $locale));

        $this->setMailable($mailable);

        return $this->mailable;
    }

    public function getMailableReceiver(?string $locale = null): Identity
    {
        $locale = $this->getUsedLocale($locale);

        $receiver = parent::getMailableReceiver($locale);

        if (isset($this->familyName)) {
            $receiver->setFamilyName($this->getPayload()->familyName, $locale);
        }

        if (isset($this->givenName)) {
            $receiver->setGivenName($this->getPayload()->givenName, $locale);
        }

        return $receiver;
    }

    /**
     * @inheritDoc
     */
    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationMessages = [];

        $invitationValidationMessages = array_merge(
            $invitationValidationMessages,
            $this->getPayload()->getValidationMessages($validationContext)
        );

        return $invitationValidationMessages;
    }

    public function getCreateInvitationController(Invitation $invitation): CreateInvitationController
    {
        return new ReviewerAccessInviteCreateController($this);
    }

    public function getReceiveInvitationController(Invitation $invitation): ReceiveInvitationController
    {
        return new ReviewerAccessInviteReceiveController($this);
    }

    public function getValidationRules(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationRules = [];

        if (
            $validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE ||
            $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE
        ) {
            $user = $this->getUserId() ? Repo::user()->get($this->getUserId(), true) : null;
            if (!($user?->hasRole(
                [Role::ROLE_ID_REVIEWER],
                $this->invitationModel->contextId
            ) ?? false)) { //if user already has reviewer permission no need to fill the userGroupsToAdd
                $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new NoUserGroupChangesRule(
                    $this->getPayload()->userGroupsToAdd
                );
            }
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new UserMustExistRule($this->getUserId());
        }

        if (
            $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE
        ) {
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new EmailMustNotExistRule($this->getEmail());
        }

        return array_merge(
            $invitationValidationRules,
            $this->getPayload()->getValidationRules($this, $validationContext)
        );
    }

    /**
     * @inheritDoc
     */
    public function updatePayload(?ValidationContext $validationContext = null): ?bool
    {
        // Encrypt the password if it exists
        // There is already a validation rule that makes username and password fields interconnected
        if (isset($this->getPayload()->username) && isset($this->getPayload()->password) && !$this->getPayload()->passwordHashed) {
            $this->getPayload()->password = Validation::encryptCredentials($this->getPayload()->username, $this->getPayload()->password);
            $this->getPayload()->passwordHashed = true;
        }

        // Call the parent updatePayload method to continue the normal update process
        return parent::updatePayload($validationContext);
    }

    /**
     * @inheritDoc
     */
    protected function pendingInvitationsToDeleteOnInvite(): Builder
    {
        $payload = $this->getPayload();

        $submissionId = $payload->submissionId ?? null;
        $reviewRoundId = $payload->reviewRoundId ?? null;

        $query = InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType($this->getType())
            ->byNotId($this->getId())
            ->when(
                isset($this->invitationModel->userId),
                fn (Builder $q) => $q->byUserId($this->invitationModel->userId)
            )
            ->when(
                !isset($this->invitationModel->userId) && $this->invitationModel->email,
                fn (Builder $q) => $q->byEmail($this->invitationModel->email)
            )
            ->when(
                isset($this->invitationModel->contextId),
                fn (Builder $q) => $q->byContextId($this->invitationModel->contextId)
            );

        // Fallback for older invitations: submissionId + reviewRoundId
        if ($submissionId !== null && $reviewRoundId !== null) {
            return $query
                ->where('payload->submissionId', $submissionId)
                ->where('payload->reviewRoundId', $reviewRoundId);
        }

        /*
         * Safety guard:
         * If we cannot safely identify duplicate invitations (i.e. neither
         * reviewAssignmentId nor submissionId + reviewRoundId are available),
         * we MUST NOT delete anything.
         *
         * Returning a query with an always-false condition ensures that the
         * caller can still call ->delete() unconditionally, while guaranteeing
         * a no-op at the database level.
         *
         * Do NOT remove this unless the calling code is changed to handle
         * a null Builder or throws explicitly in this case.
         */
        return $query->whereRaw('1 = 0');
    }
}
