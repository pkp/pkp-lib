<?php

/**
 * @file classes/mail/mailables/UserRoleAssignmentInvitationNotify.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitationNotify
 *
 * @brief Email sent when a user is invited to participate into specific roles
 */

namespace PKP\mail\mailables;

use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\invitations\userRoleAssignment\helpers\UserGroupHelper;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\mail\Mailable;
use PKP\mail\traits\AddsStyleToSymfonyMessage;
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
    use AddsStyleToSymfonyMessage;

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

        // Register style injection
        $this->registerMailCss();
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

    /**
     * Build the email section listing the given role assignments, headed by
     * the given title. Returns an empty string when there are no assignments.
     */
    private function getAllUserUserGroupSection(array $userUserGroups, Context $context, string $locale, string $title): string
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

            $userGroup = UserGroup::find($userGroupHelper->userGroupId);

            $userGroupSection = $this->getUserUserGroupSection($userGroupHelper, $userGroup, $context, $count, $locale);

            $retString .= $userGroupSection;

            $count++;
        }

        return $retString;
    }

    /**
     * Build the email text describing a role assignment: its position in
     * the list, the role's name, its start and optional end date, and whether
     * the role will appear on the context's masthead.
     */
    private function getUserUserGroupSection(UserGroupHelper $userUserGroup, UserGroup $userGroup, Context $context, int $count, string $locale): string
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

        return __(
            'emails.userRoleAssignmentInvitationNotify.userGroupSection',
            [
                'sectionNumber' => $count,
                'sectionName' => $userGroup->getLocalizedData('name', $locale),
                'dateStart' => $userUserGroup->dateStart,
                'sectionEndingDate' => $sectionEndingDate,
                'sectionMastheadAppear' => $sectionMastheadAppear
            ]
        );
    }

    /**
     * Get an identity's full name in the given locale, falling back to the site's
     * primary locale and then to the first locale with a name.
     */
    private function getLocalizedFullName(Identity $identity, string $locale): string
    {
        $fullName = $identity->getFullName(preferredLocale: $locale);
        if ($fullName !== '' || empty($identity->getGivenName(null))) {
            return $fullName;
        }
        return collect($identity->getFullNames())->filter()->first() ?? '';
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
        $userGroupsAdded = '';

        if ($this->invitation->getPayload()->userGroupsToAdd) {
            $userGroupsAdded = $this->getAllUserUserGroupSection($this->invitation->getPayload()->userGroupsToAdd, $context, $locale, $userGroupsAddedTitle);
        }

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

            $existingUserGroups .= $this->getAllUserUserGroupSection($userUserGroups->toArray(), $context, $locale, $existingUserGroupsTitle);
        }

        $recipientFullName = $this->getLocalizedFullName($sendIdentity, $locale);
        $recipientName = htmlspecialchars(!empty($recipientFullName) ? $recipientFullName : $sendIdentity->getEmail());

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$recipientName => $recipientName,
                static::$inviterName => $inviter ? htmlspecialchars($this->getLocalizedFullName($inviter, $locale)) : null,
                static::$acceptUrl => $this->invitation->getActionURL(InvitationAction::ACCEPT),
                static::$declineUrl => $this->invitation->getActionURL(InvitationAction::DECLINE),
                static::$rolesAdded => $userGroupsAdded,
                static::$existingRoles => $existingUserGroups,
            ]
        );
    }
}
