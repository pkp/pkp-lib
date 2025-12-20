<?php

/**
 * @file classes/mail/mailables/UserRoleAssignmentInvitationNotify.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitationNotify
 *
 * @brief Email sent when a user is invited to participate into specific roles
 */

namespace PKP\mail\mailables;

use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\invitations\userRoleAssignment\helpers\UserGroupHelper;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;

class UserRoleAssignmentInvitationNotify extends Mailable
{
    use Recipient;
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.userRoleAssignmentInvitationNotify.name';
    protected static ?string $description = 'mailable.userRoleAssignmentInvitationNotify.description';
    protected static ?string $emailTemplateKey = 'USER_ROLE_ASSIGNMENT_INVITATION';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        self::FROM_SYSTEM,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    protected static string $recipientName = 'recipientName';
    protected static string $inviterName = 'inviterName';
    protected static string $inviterRole = 'inviterRole';
    protected static string $rolesAdded = 'rolesAdded';
    protected static string $existingRoles = 'existingRoles';
    protected static string $acceptUrl = 'acceptUrl';
    protected static string $declineUrl = 'declineUrl';

    private UserRoleAssignmentInvite $invitation;

    public function __construct(Context $context, UserRoleAssignmentInvite $invitation)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));

        $this->invitation = $invitation;
    }

    /**
     * Add description to a new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();

        $variables[static::$recipientName] = __('emailTemplate.variable.invitation.recipientName');
        $variables[static::$inviterName] = __('emailTemplate.variable.invitation.inviterName');
        $variables[static::$inviterRole] = __('emailTemplate.variable.invitation.inviterRole');
        $variables[static::$rolesAdded] = __('emailTemplate.variable.invitation.rolesAdded');
        $variables[static::$existingRoles] = __('emailTemplate.variable.invitation.existingRoles');
        $variables[static::$acceptUrl] = __('emailTemplate.variable.invitation.acceptUrl');
        $variables[static::$declineUrl] = __('emailTemplate.variable.invitation.declineUrl');

        return $variables;
    }

    private function getAllUserUserGroupSection(array $userUserGroups, ?UserGroup $userGroup = null, Context $context, string $locale, string $title): string
    {
        $retString = '';

        $count = 1;
        foreach ($userUserGroups as $userUserGroup) {
            $userGroupHelper = $userUserGroup instanceof UserUserGroup
                ? UserGroupHelper::fromUserUserGroup($userUserGroup)
                : UserGroupHelper::fromArray($userUserGroup);

            if ($count == 1) {
                $retString = $title;
            }

            $userGroupToUse = $userGroup ?? UserGroup::find($userGroupHelper->userGroupId);

            $userGroupSection = $this->getUserUserGroupSection($userGroupHelper, $userGroupToUse, $context, $count, $locale);

            $retString .= $userGroupSection;

            $count++;
        }

        return $retString;
    }

    private function getUserUserGroupSection(UserGroupHelper $userUserGroup, UserGroup $userGroup, Context $context, int  $count, string $locale): string
    {
        $sectionEndingDate = '';
        if (isset($userUserGroup->dateEnd)) {
            $sectionEndingDate = __(
                'emails.userRoleAssignmentInvitationNotify.userGroupSectionEndingDate',
                [
                    'dateEnd' => $userUserGroup->dateEnd
                ]
            );
        }

        $sectionMastheadAppear = __(
            'emails.userRoleAssignmentInvitationNotify.userGroupSectionWillNotAppear',
            [
                'contextName' => $context->getName($locale),
                'sectionName' => $userGroup->getLocalizedData('name', $locale)
            ]
        );

        if (isset($userUserGroup->masthead) && $userUserGroup->masthead) {
            $sectionMastheadAppear = __(
                'emails.userRoleAssignmentInvitationNotify.userGroupSectionWillAppear',
                [
                    'contextName' => $context->getName($locale),
                    'sectionName' => $userGroup->getLocalizedData('name', $locale)
                ]
            );
        }

        $userGroupSection = __(
            'emails.userRoleAssignmentInvitationNotify.userGroupSection',
            [
                'sectionNumber' => $count,
                'sectionName' => $userGroup->getLocalizedData('name', $locale),
                'dateStart' => $userUserGroup->dateStart,
                'sectionEndingDate' => $sectionEndingDate,
                'sectionMastheadAppear' => $sectionMastheadAppear
            ]
        );

        return $userGroupSection;
    }


    /**
     * Set localized email template variables
     */
    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        if (is_null($locale)) {
            $locale = $this->getLocale() ?? Locale::getLocale();
        }

        // Invitation User
        $sendIdentity = $this->invitation->getMailableReceiver($locale);

        // Inviter
        $user = $this->invitation->getExistingUser();
        $inviter = $this->invitation->getInviter();

        $context = $this->invitation->getContext();

        // Roles Added
        $userGroupsAddedTitle = __('emails.userRoleAssignmentInvitationNotify.newlyAssignedRoles');
        $userGroupsAdded = $this->getAllUserUserGroupSection($this->invitation->getPayload()->userGroupsToAdd, null, $context, $locale, $userGroupsAddedTitle);


        $existingUserGroupsTitle = __('emails.userRoleAssignmentInvitationNotify.alreadyAssignedRoles');
        $existingUserGroups = '';

        if (isset($user)) {
            // Existing Roles

            /** @var Collection<UserGroup> $userGroups */
            $userGroups = UserGroup::query()
                ->withContextIds([$this->invitation->getContextId()])
                ->withUserIds([$user->getId()])
                ->get();

            /** @var Collection<UserUserGroup> $userUserGroups */
            $userUserGroups = $userGroups->reduce(function (Collection $userUserGroups, UserGroup $userGroup) use ($user) {
                UserUserGroup::withUserId($user->getId())
                    ->withUserGroupIds([$userGroup->id])
                    ->withActive()
                    ->get()
                    ->each(fn (UserUserGroup $userUserGroup) => $userUserGroups->add($userUserGroup));

                return $userUserGroups;
            }, collect());

            $existingUserGroups .= $this->getAllUserUserGroupSection($userUserGroups->toArray(), null, $context, $locale, $existingUserGroupsTitle);
        }

        $targetPath = Core::getBaseDir() . '/lib/pkp/styles/mailables/style.css';
        $emailTemplateStyle = file_get_contents($targetPath);

        $recipientName = !empty($sendIdentity->getFullName()) ? $sendIdentity->getFullName() : $sendIdentity->getEmail();

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$recipientName => $recipientName,
                static::$inviterName => $inviter->getFullName(),
                static::$acceptUrl => $this->invitation->getActionURL(InvitationAction::ACCEPT),
                static::$declineUrl => $this->invitation->getActionURL(InvitationAction::DECLINE),
                static::$rolesAdded => $userGroupsAdded,
                static::$existingRoles => $existingUserGroups,
                static::EMAIL_TEMPLATE_STYLE_PROPERTY => $emailTemplateStyle,
            ]
        );
    }
}
