<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/rules/PrimaryLocaleRequired .php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrimaryLocaleRequired 
 *
 * @brief Primary Locale required for mandatory multilingual fields
 */

namespace PKP\invitation\invitations\userRoleAssignment\rules;

use Illuminate\Contracts\Validation\Rule;

class PrimaryLocaleRequired implements Rule
{
    protected $primaryLocale;

    public function __construct($primaryLocale)
    {
        $this->primaryLocale = $primaryLocale;
    }

    public function passes($attribute, $value)
    {
        $providedLocales = array_keys($value);
        if (!empty($providedLocales) && 
            array_key_exists($this->primaryLocale, $value) && 
            empty($value[$this->primaryLocale])) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return __('invitation.userRoleAssignment.validation.error.multilingual.primaryLocaleRequired', [
            'primaryLocale' => $this->primaryLocale,
        ]);
    }
}