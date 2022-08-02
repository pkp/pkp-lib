<?php

/**
 * @file classes/validation/ValidatorISSN.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorISSN
 * @ingroup validation
 *
 * @see Validator
 *
 * @brief Validation check for ISSNs.
 */

namespace PKP\validation;

class ValidatorISSN extends Validator
{
    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => ['required', 'issn']]
        );

        return $validator->passes();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorISSN', '\ValidatorISSN');
}
