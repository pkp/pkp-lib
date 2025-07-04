<?php

/**
 * @file classes/validation/ValidatorUrl.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorUrl
 *
 * @ingroup validation
 *
 * @see Validator
 *
 * @brief Validation check for URLs.
 */

namespace PKP\validation;

class ValidatorUrl extends Validator
{
    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => ['required', 'url']]
        );

        return $validator->passes();
    }
}
