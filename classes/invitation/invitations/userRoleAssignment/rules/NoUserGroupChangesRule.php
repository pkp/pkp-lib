<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/NoUserGroupChangesRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoUserGroupChangesRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Rule;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

class NoUserGroupChangesRule implements Rule
{
    protected UserRoleAssignmentInvite $invitation;
    protected string $validationContext;

    public function __construct(UserRoleAssignmentInvite $invitation, string $validationContext = Invitation::VALIDATION_CONTEXT_DEFAULT)
    {
        $this->invitation = $invitation;
        $this->validationContext = $validationContext;
    }

    public function passes($attribute, $value)
    {
        if (
            $this->validationContext === Invitation::VALIDATION_CONTEXT_INVITE ||
            $this->validationContext === Invitation::VALIDATION_CONTEXT_FINALIZE) {
            return !(
                empty($this->invitation->getSpecificPayload()->userGroupsToAdd) && 
                empty($this->invitation->getSpecificPayload()->userGroupsToRemove)
            );
        }

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.noUserGroupChanges');
    }
}