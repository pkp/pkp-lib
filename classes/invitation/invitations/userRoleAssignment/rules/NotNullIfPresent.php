<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/ProhibitedIncludingNull.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProhibitedIncludingNull
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;

class NotNullIfPresent implements Rule
{
    public function passes($attribute, $value)
    {
        // Fail validation if the field is explicitly set to null
        return !is_null($value);
    }

    public function message()
    {
        return 'The :attribute field cannot be null.';
    }
}