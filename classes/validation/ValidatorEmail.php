<?php

/**
 * @file classes/validation/ValidatorEmail.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorEmail
 *
 * @ingroup validation
 *
 * @see Validator
 *
 * @brief Validation check for email addresses.
 */

namespace PKP\validation;

class ValidatorEmail extends Validator
{
    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => ['required', 'email_or_localhost']]
        );

        return $validator->passes();
    }
}
