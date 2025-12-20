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

class ProhibitedIncludingNull implements Rule
{
    protected $condition;

    public function __construct($condition)
    {
        $this->condition = $condition;
    }

    public function passes($attribute, $value)
    {
        // If the condition is true, prohibit both null and non-null values
        if ($this->condition) {
            return $value === null ? false : false;
        }

        return true;
    }

    public function message()
    {
        return __('invitation.validation.error.propertyProhibited', [
            'attribute' => ':attribute',
        ]);
    }
}