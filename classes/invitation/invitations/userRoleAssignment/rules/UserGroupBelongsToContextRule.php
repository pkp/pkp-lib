<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/UserGroupBelongsToContextRule.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupBelongsToContextRule
 *
 * @brief Ensures a user group being assigned through an invitation belongs to the invitation's context.
 *
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;
use PKP\core\PKPApplication;
use PKP\invitation\core\Invitation;
use PKP\userGroup\UserGroup;

class UserGroupBelongsToContextRule implements Rule
{
    protected Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function passes($attribute, $value)
    {
        $userGroup = UserGroup::find($value);

        // Existence is checked by UserGroupExistsRule.
        if (!$userGroup) {
            return true;
        }

        return $userGroup->contextId === $this->invitation->getContextId();
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.addUserRoles.userGroupNotInContext');
    }
}
