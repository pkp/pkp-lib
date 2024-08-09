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
use Exception;
use Illuminate\Mail\Mailable;
use PKP\core\Core;
use PKP\identity\Identity;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentCreateController;
use PKP\invitation\invitations\userRoleAssignment\handlers\api\UserRoleAssignmentReceiveController;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteRedirectController;
use PKP\invitation\invitations\userRoleAssignment\payload\UserGroupPayload;
use PKP\invitation\models\InvitationModel;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use PKP\security\Validation;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\UserUserGroup;

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
    ];

    public ?string $orcid = null;
    public ?string $givenName = null;
    public ?string $familyName = null;
    public ?string $affiliation = null;
    public ?string $country = null;

    public ?string $username = null;
    public ?string $password = null;

    public ?string $emailSubject = null;
    public ?string $emailBody = null;
    public ?bool $existingUser = null;

    public ?array $userGroupsToAdd = null;

    public ?array $userGroupsToRemove = null;

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
            $receiver->setFamilyName($this->familyName, $locale);
        }

        if (isset($this->givenName)) {
            $receiver->setGivenName($this->givenName, $locale);
        }

        return $receiver;
    }

    protected function preInviteActions(): void
    {
        // Check if everything is in order regarding the properties
        if (empty($this->userGroupsToAdd) && empty($this->userGroupsToRemove)) {
            throw new Exception(__('invitation.userRoleAssignment.validation.error.noUserGroupChanges'));
        }

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

    public function finalize(): void
    {
        $user = null;

        if ($this->invitationModel->userId) {
            $user = Repo::user()->get($this->invitationModel->userId);

            if (!isset($user)) {
                throw new Exception('The user does not exist');
            }
        }
        else if ($this->invitationModel->email) {
            $user = Repo::user()->getByEmail($this->invitationModel->email);

            if (!isset($user)) {
                $user = Repo::user()->newDataObject();

                $user->setUsername($this->username);

                // Set the base user fields (name, etc.)
                $user->setGivenName($this->givenName, null);
                $user->setFamilyName($this->familyName, null);
                $user->setEmail($this->invitationModel->email);
                $user->setCountry($this->country);
                $user->setAffiliation($this->affiliation, null);

                $user->setOrcid($this->orcid);

                $user->setDateRegistered(Core::getCurrentDate());
                $user->setInlineHelp(1); // default new users to having inline help visible.
                $user->setPassword(Validation::encryptCredentials($this->username, $this->password));

                Repo::user()->add($user);
            }
        }

        foreach ($this->userGroupsToRemove as $userUserGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);
            Repo::userGroup()-> deleteAssignmentsByUserId(
                $user->getId(),
                $userGroupPayload->userGroupId
            );
        }

        foreach ($this->userGroupsToAdd as $userUserGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);

            Repo::userGroup()->assignUserToGroup(
                $user->getId(),
                $userGroupPayload->userGroupId,
                $userGroupPayload->dateStart,
                $userGroupPayload->dateEnd,
                isset($userGroupPayload->masthead) && $userGroupPayload->masthead 
                    ? UserUserGroupMastheadStatus::STATUS_ON 
                    : UserUserGroupMastheadStatus::STATUS_OFF
            );
        }

        $this->invitationModel->markAs(InvitationStatus::ACCEPTED);
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
    public function validate(): bool
    {
        // Custom rules
        if (isset($this->userGroupsToAdd)) {
            if (empty($this->userGroupsToAdd)) {
                $this->addError('userGroupsToAdd', __('invitation.userRoleAssignment.validation.error.addUserRoles.notExisting'));
            }
        }

        if (isset($this->userGroupsToRemove)) {
            if (isset($userId)) {
                $user = $this->getExistingUser();

                if (empty($this->userGroupsToRemove)) {
                    $this->addError('userGroupsToRemove', __('invitation.userRoleAssignment.validation.error.removeUserRoles.notExisting'));
                } else {
                    foreach ($this->userGroupsToRemove as $userUserGroup) {
                        $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);
                        
                        $userGroup = Repo::userGroup()->get($userGroupPayload->userGroupId);
                        $userUserGroups = UserUserGroup::withUserId($user->getId())
                            ->withUserGroupId($userGroup->getId())
                            ->get();

                        if (empty($userUserGroups)) {
                            $this->addError('userGroupsToRemove', __('invitation.userRoleAssignment.validation.error.removeUserRoles.userDoesNotHaveRoles'));
                        } 
                    }
                }
            } else {
                $this->addError('userGroupsToRemove', __('invitation.userRoleAssignment.validation.error.removeUserRoles.cantRemoveFromNonExistingUser'));
            }
        }

        return $this->isValid();
    }

    /**
     * {@inheritDoc}
     */
    public function fillCustomProperties(): void
    {
        $this->fillInvitationWithUserGroups();
    }

    public function fillInvitationWithUserGroups(): void
    {
        if (isset($this->userGroupsToAdd)) {
            $this->fillArrayWithUserGroups($this->userGroupsToAdd);
        }

        if (isset($this->userGroupsToRemove)) {
            $this->fillArrayWithUserGroups($this->userGroupsToRemove);
        }
    }

    private function fillArrayWithUserGroups(array &$userGroups): void
    {
        $userGroups = array_map(function ($userGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userGroup);
            $userGroupPayload->getUserGroupName();
            $userGroup['userGroupPayload'] = $userGroupPayload;
            return $userGroup;
        }, $userGroups);
    }
}
