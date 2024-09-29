<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/AddUserGroupRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddUserGroupRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;
use PKP\invitation\core\Invitation;
use PKP\userGroup\relationships\UserUserGroup;

class AddUserGroupRule implements Rule
{
    protected Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function passes($attribute, $value)
    {
        // At this point, we know the user group exists; check if the user has it assigned
        if ($user = $this->invitation->getExistingUser()) {
            $userUserGroups = UserUserGroup::withUserId($user->getId())
                ->withUserGroupId($value) // The $value is the userGroupId
                ->get();

            return $userUserGroups->isEmpty(); // Fail if the user does have the group assigned
        }

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupAssignedToUser');
    }
}