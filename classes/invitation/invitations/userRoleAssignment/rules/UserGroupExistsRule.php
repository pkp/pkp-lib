<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/UserGroupExistsRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupExistsRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Rule;

class UserGroupExistsRule implements Rule
{
    protected $userGroupId;
    public function passes($attribute, $value)
    {
        $this->userGroupId = $value;
        $userGroup = Repo::userGroup()->get($value);
        return isset($userGroup);
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupNotExisting', [
            'userGroupId' => $this->userGroupId,
        ]);
    }
}