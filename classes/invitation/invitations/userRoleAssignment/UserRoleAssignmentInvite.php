<?php

/**
 * @file classes/invitation/invitations/UserRoleAssignmentInvite.php
 *
 * Copyright (c) 2024-2026 Simon Fraser University
 * Copyright (c) 2024-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvite
 *
 * @brief Assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use PKP\identity\Identity;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\InvitationUIActionRedirectController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentCreateController;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentReceiveController;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteRedirectController;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteUIController;
use PKP\invitation\invitations\userRoleAssignment\payload\UserRoleAssignmentInvitePayload;
use PKP\invitation\invitations\userRoleAssignment\rules\EmailMustNotExistRule;
use PKP\invitation\invitations\userRoleAssignment\rules\NoUserGroupChangesRule;
use PKP\invitation\invitations\userRoleAssignment\rules\UserMustExistRule;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use PKP\security\Validation;

class UserRoleAssignmentInvite extends Invitation implements IApiHandleable
{
    use HasMailable;
    use ShouldValidate;

    public const INVITATION_TYPE = 'userRoleAssignment';

    protected array $notAccessibleAfterInvite = [
        'userGroupsToAdd',
    ];

    protected array $notAccessibleBeforeInvite = [
        'orcid',
        'username',
        'password'
    ];

    /**
     * Get the type key that identifies this kind of invitation.
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
        return UserRoleAssignmentInvitePayload::class;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): UserRoleAssignmentInvitePayload
    {
        return parent::getPayload();
    }

    /**
     * Get the payload properties that cannot be changed once the invitation
     * has been sent, so the invitee cannot change the roles they were offered.
     */
    public function getNotAccessibleAfterInvite(): array
    {
        return array_merge(parent::getNotAccessibleAfterInvite(), $this->notAccessibleAfterInvite);
    }

    /**
     * Get the payload properties that cannot be set before the invitation is
     * sent. These are the invitee's account details, which only the invitee
     * provides when accepting.
     */
    public function getNotAccessibleBeforeInvite(): array
    {
        return array_merge(parent::getNotAccessibleBeforeInvite(), $this->notAccessibleBeforeInvite);
    }

    /**
     * Build the invitation email in the context's primary locale, using the
     * subject and body the inviter wrote, otherwise the email template's.
     *
     * @throws \Exception
     */
    public function getMailable(): Mailable
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);
        $locale = $context->getPrimaryLocale();

        // Define the Mailable
        $mailable = new UserRoleAssignmentInvitationNotify($context, $this);
        $mailable->setLocale($locale)->setData($locale);

        // Set the email send data
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

        if (!isset($emailTemplate)) {
            throw new \Exception('No email template found for key ' . $mailable::getEmailTemplateKey());
        }

        $inviter = $this->getInviter();
        $receiver = $this->getMailableReceiver($locale);

        $emailComposerValues = $this->getPayload()->emailComposer;
        $emailSubject = $emailTemplate->getLocalizedData('subject', $locale);
        $emailBody = $emailTemplate->getLocalizedData('body', $locale);

        if (isset($emailComposerValues)) {
            $emailSubject = $emailComposerValues['subject'] ?? $emailSubject;
            $emailBody = $emailComposerValues['body'] ?? $emailBody;
        }

        $mailable
            ->sender($inviter)
            ->recipients([$receiver], $locale)
            ->subject($emailSubject)
            ->body($emailBody);

        $this->setMailable($mailable);

        return $this->mailable;
    }

    /**
     * Get the identity that will receive the invitation email.
     *
     * Names provided in the invitation payload are used in every locale they
     * were entered in, so that new users who do not yet have an account are
     * addressed by name rather than by email address. When there is no name in
     * the given locale or the site's primary locale, the first locale with a
     * name is used so that the name in the email headers matches the greeting in the body.
     */
    public function getMailableReceiver(?string $locale = null): Identity
    {
        $locale = $this->getUsedLocale($locale);

        $receiver = parent::getMailableReceiver($locale);
        $payload = $this->getPayload();

        foreach (array_filter($payload->familyName ?? []) as $nameLocale => $familyName) {
            $receiver->setFamilyName($familyName, $nameLocale);
        }

        foreach (array_filter($payload->givenName ?? []) as $nameLocale => $givenName) {
            $receiver->setGivenName($givenName, $nameLocale);
        }

        $fallbackLocale = array_key_first(array_filter((array) $receiver->getGivenName(null)));
        if ($fallbackLocale !== null && $receiver->getFullName(preferredLocale: $locale) === '') {
            $receiver->setGivenName($receiver->getGivenName($fallbackLocale), $locale);
            $receiver->setFamilyName($receiver->getFamilyName($fallbackLocale), $locale);
        }

        return $receiver;
    }

    /**
     * Get the controller that handles the accept and decline links in the invitation email.
     */
    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new UserRoleAssignmentInviteRedirectController($this);
    }

    /**
     * Get the controller that shows the pages for creating and editing this invitation.
     */
    public function getInvitationUIActionRedirectController(): ?InvitationUIActionRedirectController
    {
        return new UserRoleAssignmentInviteUIController($this);
    }

    /**
     * @inheritDoc
     */
    public function getCreateInvitationController(Invitation $invitation): CreateInvitationController
    {
        return new UserRoleAssignmentCreateController($invitation);
    }

    /**
     * @inheritDoc
     */
    public function getReceiveInvitationController(Invitation $invitation): ReceiveInvitationController
    {
        return new UserRoleAssignmentReceiveController($invitation);
    }

    /**
     * Get the validation rules for the given validation context.
     *
     * When sending or accepting, at least one role must be offered and an
     * invited existing user must still exist. When accepting, a new user's
     * email must not belong to an account. The payload's own rules are added
     * in every context.
     */
    public function getValidationRules(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationRules = [];

        if (
            $validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE ||
            $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE
        ) {
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new NoUserGroupChangesRule(
                $this->getPayload()->userGroupsToAdd
            );
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
    public function getValidationMessages(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationMessages = [];

        return array_merge(
            $invitationValidationMessages,
            $this->getPayload()->getValidationMessages($validationContext)
        );
    }

    /**
     * @inheritDoc
     */
    public function updatePayload(?ValidationContext $validationContext = null): ?bool
    {
        // Encrypt the password if it exists
        // There is already a validation rule that makes username and password fields interconnected
        if (
            isset($this->getPayload()->username) &&
            isset($this->getPayload()->password) &&
            !$this->getPayload()->passwordHashed
        ) {
            $this->getPayload()->password = Validation::encryptCredentials($this->getPayload()->username, $this->getPayload()->password);
            $this->getPayload()->passwordHashed = true;
        }

        // Call the parent updatePayload method to continue the normal update process
        return parent::updatePayload($validationContext);
    }

    /**
     * Link the invitation to an existing account with the invited email if one exists.
     * Returns the result of updating the payload, or null when no account was linked.
     */
    public function changeInvitationUserIdUsingUserEmail(): ?bool
    {
        $invitationUserByEmail = $this->getExistingUserByEmail();

        if (!isset($this->invitationModel->userId) && isset($invitationUserByEmail)) {
            $this->invitationModel->userId = $invitationUserByEmail->getId();
            $this->invitationModel->email = null;

            $result =  $this->invitationModel->save();

            if ($result) {
                $this->getPayload()->shouldUseInviteData = true;

                return $this->updatePayload();
            }
        }

        return null;
    }
}
