<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/AllowedKeysRule.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowedKeysRule
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;

class AllowedKeysRule implements Rule
{
    protected $attribute;
    protected $allowedKeys;
    protected $unexpectedKeys = [];

    public function __construct(array $allowedKeys)
    {
        $this->allowedKeys = $allowedKeys;
    }

    public function passes($attribute, $value)
    {
        $this->unexpectedKeys = array_diff(array_keys($value), $this->allowedKeys);
        return empty($this->unexpectedKeys);
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.userRoles.unexpectedProperties', [
            'attribute' => ':attribute',
            'properties' => implode(', ', $this->unexpectedKeys),
        ]);
    }
}