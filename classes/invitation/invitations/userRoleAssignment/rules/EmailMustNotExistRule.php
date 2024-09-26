<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/EmailMustNotExistRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailMustNotExistRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Rule;
use PKP\invitation\core\Invitation;

class EmailMustNotExistRule implements Rule
{
    protected ?string $email;

    public function __construct(?string $email)
    {
        $this->email = $email;
    }

    public function passes($attribute, $value)
    {
        if ($this->email) {
            $user = Repo::user()->getByEmail($this->email);
            return !isset($user);  // Fail if the email is already associated with a user
        }

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.user.emailMustNotExist', [
            'email' => $this->email,
        ]);
    }
}