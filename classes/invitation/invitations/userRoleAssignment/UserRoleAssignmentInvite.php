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
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\InvitePayload;
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
use PKP\invitation\models\InvitationModel;
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
            $receiver->setFamilyName($this->getSpecificPayload()->familyName, $locale);
        }

        if (isset($this->givenName)) {
            $receiver->setGivenName($this->getSpecificPayload()->givenName, $locale);
        }

        return $receiver;
    }

    protected function preInviteActions(): void
    {
        // Invalidate any other related invitation
        InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->when(isset($this->invitationModel->userId), function ($query) {
                return $query->byUserId($this->invitationModel->userId);
            })
            ->when(!isset($this->invitationModel->userId) && $this->invitationModel->email, function ($query) {
                return $query->byEmail($this->invitationModel->email);
            })
            ->byContextId($this->invitationModel->contextId)
            ->delete();
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

    /**
     * @inheritDoc
     */
    protected function createPayload(): InvitePayload
    {
        return new UserRoleAssignmentInvitePayload();
    }

    /**
     * Access the UserRoleAssignmentInvitePayload properties.
     */
    public function getSpecificPayload(): UserRoleAssignmentInvitePayload
    {
        return $this->payload;
    }

    public function getValidationRules(string $validationContext = Invitation::VALIDATION_CONTEXT_DEFAULT): array
    {
        $invitationValidationRules = [
            Invitation::VALIDATION_RULE_GENERIC => [
                new NoUserGroupChangesRule($this, $validationContext),
                new UserMustExistRule($this, $validationContext),
                new EmailMustNotExistRule($this->getEmail(), $validationContext),
            ],
        ];

        $validationRules = array_merge(
            $invitationValidationRules, 
            $this->getSpecificPayload()->getValidationRules($this, $validationContext)
        );

        return $validationRules;
    }

    /**
     * @inheritDoc
     */
    public function updatePayload(?string $validationContext = null): ?bool
    {
        // Encrypt the password if it exists
        // There is already a validation rule that makes username and password fields interconnected
        if (isset($this->getSpecificPayload()->username) && isset($this->getSpecificPayload()->password) && !$this->getSpecificPayload()->passwordHashed) {
            $this->getSpecificPayload()->password = Validation::encryptCredentials($this->getSpecificPayload()->username, $this->getSpecificPayload()->password);
            $this->getSpecificPayload()->passwordHashed = true;
        }

        // Call the parent updatePayload method to continue the normal update process
        return parent::updatePayload($validationContext);
    }
}
