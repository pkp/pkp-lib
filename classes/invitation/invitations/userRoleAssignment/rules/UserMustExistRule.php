<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/UserMustExistRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserMustExistRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

class UserMustExistRule implements Rule
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
            $userId = $this->invitation->getUserId();

            if (isset($userId)) {
                $user = $this->invitation->getExistingUser();
                return isset($user);  // Ensure user exists
            }
        }
        

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.user.mustExist', [
            'userId' => $this->invitation->getUserId(),
        ]);
    }
}