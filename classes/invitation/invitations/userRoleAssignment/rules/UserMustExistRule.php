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

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Rule;

class UserMustExistRule implements Rule
{
    protected ?int $userId;

    public function __construct(?int $userId)
    {
        $this->userId = $userId;
    }

    public function passes($attribute, $value)
    {
        if (isset($this->userId)) {
            $user = Repo::user()->get($this->userId);
            return isset($user);  // Ensure user exists
        }

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.user.mustExist', [
            'userId' => $this->userId,
        ]);
    }
}