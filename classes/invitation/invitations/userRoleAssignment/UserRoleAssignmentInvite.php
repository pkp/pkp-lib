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

    protected array $validateFunctions = [
        'givenName' => 'validateGivenName',
        'userGroupsToAdd' => 'validateUserGroupsToAdd',
        'userGroupsToRemove' => 'validateUserGroupsToRemove',
        'familyName' => 'validateFamilyName',
        'affiliation' => 'validateAffiliation',
        'country' => 'validateCountry',
        'username' => 'validateUsername',
        'password' => 'validatePassword',
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
        if (empty($this->userGroupsToAdd) && empty($this->userGroupsToRemove)) {
            $this->addError('', __('invitation.userRoleAssignment.validation.error.noUserGroupChanges'));
            return;
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

    public function validateUserGroupsToAdd()
    {
        if (empty($this->userGroupsToAdd)) {
            $this->addError('userGroupsToAdd', __('invitation.userRoleAssignment.validation.error.addUserRoles.notExisting'));

            return;
        }

        $seenUserGroupIds = [];

        foreach ($this->userGroupsToAdd as $userUserGroup) {
            $shouldCheckGroup = true;
            if (!isset($userUserGroup['userGroupId'])) {
                $this->addError('userGroupsToAdd.userGroupId', __('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupIdMandatory'));
                $shouldCheckGroup = false;
            }

            if (!isset($userUserGroup['dateStart'])) {
                $this->addError('userGroupsToAdd.dateStart', __('invitation.userRoleAssignment.validation.error.addUserRoles.dateStartMandatory'));
            }

            if (!isset($userUserGroup['masthead'])) {
                $this->addError('userGroupsToAdd.masthead', __('invitation.userRoleAssignment.validation.error.addUserRoles.mastheadMandatory'));
            }

            if ($shouldCheckGroup) {
                $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);
                $userGroup = Repo::userGroup()->get($userGroupPayload->userGroupId);

                if (!isset($userGroup)) {
                    $this->addError('userGroupsToAdd', __('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupNotExisting', 
                        [
                            'userGroupId' => $userGroupPayload->userGroupId
                        ])
                    );

                    continue;
                }

                if (isset($seenUserGroupIds[$userGroupPayload->userGroupId])) {
                    // Duplicate userGroupId found
                    $this->addError('userGroupsToAdd', __('invitation.userRoleAssignment.validation.error.addUserRoles.duplicateUserGroupId', 
                        [
                            'userGroupId' => $userGroupPayload->userGroupId,
                            'userGroupName' => $userGroup->getLocalizedName()
                        ])
                    );

                    // Skip processing this duplicate entry
                    continue; 
                }

                // Mark this userGroupId as seen
                $seenUserGroupIds[$userGroupPayload->userGroupId] = true;
            }

            
        }
    }

    public function validateUserGroupsToRemove()
    {
        $user = $this->getExistingUser();

        if (!isset($user)) {
            $this->addError('userGroupsToRemove', __('invitation.userRoleAssignment.validation.error.removeUserRoles.cantRemoveFromNonExistingUser'));
            return;
        }

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

    public function validateUsername()
    {
        $existingUser = Repo::user()->getByUsername($this->username, true);

        if (isset($existingUser)) {
            $this->addError('username', __('invitation.userRoleAssignment.validation.error.username.alreadyExisting', 
                [
                    'username' => $this->username
                ])
            );
        }
    }

    public function validateAffiliation()
    {
        if (empty($this->affiliation)) {
            $this->addError('affiliation', __('invitation.userRoleAssignment.validation.error.affiliation.mandatory'));
        }
    }

    public function validateGivenName()
    {
        if (empty($this->givenName)) {
            $this->addError('givenName', __('invitation.userRoleAssignment.validation.error.givenName.mandatory'));
        }
    }

    public function validateFamilyName()
    {
        if (empty($this->givenName)) {
            $this->addError('familyName', __('invitation.userRoleAssignment.validation.error.familyName.mandatory'));
        }
    }

    public function validateCountry()
    {
        if (empty($this->country)) {
            $this->addError('country', __('invitation.userRoleAssignment.validation.error.country.mandatory'));
        }
    }

    public function validatePassword()
    {
        if (empty($this->country)) {
            $this->addError('password', __('invitation.userRoleAssignment.validation.error.password.mandatory'));
        }
    }

    public function validateBeforeFinalise()
    {
        $userId = $this->getUserId();

        if (isset($userId)) {
            $user = $this->getExistingUser();

            if (!isset($user)) {
                $this->addError('', __('invitation.userRoleAssignment.validation.error.user.mustExist',
                    [
                        'userId' => $userId
                    ])
                );
            }
        }
        else if ($this->getEmail()) {
            $user = Repo::user()->getByEmail($this->getEmail());

            if (isset($user)) {
                $this->addError('', __('invitation.userRoleAssignment.validation.error.user.emailMustNotExist', 
                    [
                        'email' => $this->getEmail()
                    ])
                );
            }
        }

        $this->validateUsername();
        $this->validatePassword();
        $this->validateAffiliation();
        $this->validateCountry();
        $this->validateGivenName();
        $this->validateFamilyName();
    }

    /**
     * @inheritDoc
     */
    public function validate(): bool
    {
        foreach ($this->currentlyFilledFromArgs as $property) {
            if (isset($this->validateFunctions[$property])) {
                $function = $this->validateFunctions[$property];
                $this->$function();
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
