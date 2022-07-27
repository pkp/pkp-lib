<?php

/**
 * @file classes/validation/ValidatorRegExp.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorRegExp
 * @ingroup validation
 *
 * @brief Validation check using a regular expression.
 */

namespace PKP\validation;

class ValidatorRegExp extends Validator
{
    /** @var string The regular expression to match against the field value */
    public $_regExp;

    /**
     * Constructor.
     *
     * @param string $regExp the regular expression (PCRE form)
     */
    public function __construct($regExp)
    {
        $this->_regExp = $regExp;
    }

    /**
     * @copydoc Validator::isValid()
     */
    public function isValid($value)
    {
        $validator = ValidatorFactory::make(
            ['value' => $value],
            ['value' => ['required', 'regex:' . $this->_regExp]]
        );

        return $validator->passes();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorRegExp', '\ValidatorRegExp');
}
