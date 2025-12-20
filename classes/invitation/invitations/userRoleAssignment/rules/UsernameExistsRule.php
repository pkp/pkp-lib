<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/UsernameExistsRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsernameExistsRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Rule;

class UsernameExistsRule implements Rule
{
    protected string $username;

    public function passes($attribute, $value)
    {
        if (isset($value)) {
            $this->username = $value;
            $existingUser = Repo::user()->getByUsername($value, true);
            return !isset($existingUser);  // Fail if the username already exists
        }
        
        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.username.alreadyExisting', [
            'username' => $this->username,
        ]);
    }
}