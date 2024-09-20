<?php

/**
 * @file classes/invitation/invitations/UserRoleAssignmentInvite.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentCreateController;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentReceiveController;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteRedirectController;
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
        'userGroupsToRemove',
    ];

    protected array $notAccessibleBeforeInvite = [
        'orcid',
        'username',
        'password'
    ];

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

    public function getNotAccessibleAfterInvite(): array
    {
        return array_merge(parent::getNotAccessibleAfterInvite(), $this->notAccessibleAfterInvite);
    }

    public function getNotAccessibleBeforeInvite(): array
    {
        return array_merge(parent::getNotAccessibleBeforeInvite(), $this->notAccessibleBeforeInvite);
    }

    public function getMailable(): Mailable
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);
        $locale = $context->getPrimaryLocale();

        // Define the Mailable
        $mailable = new UserRoleAssignmentInvitationNotify($context, $this);
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

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new UserRoleAssignmentInviteRedirectController($this);
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

    public function getValidationRules(ValidationContext $validationContext = ValidationContext::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationRules = [];

        if (
            $validationContext === ValidationContext::VALIDATION_CONTEXT_INVITE ||
            $validationContext === ValidationContext::VALIDATION_CONTEXT_FINALIZE
        ) {
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new NoUserGroupChangesRule(
                $this->getPayload()->userGroupsToAdd, 
                $this->getPayload()->userGroupsToRemove
            );
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new UserMustExistRule($this->getUserId());
            $invitationValidationRules[Invitation::VALIDATION_RULE_GENERIC][] = new EmailMustNotExistRule($this->getEmail());
        }

        $validationRules = array_merge(
            $invitationValidationRules, 
            $this->getPayload()->getValidationRules($this, $validationContext)
        );

        return $validationRules;
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
    
}
