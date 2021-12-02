<?php

/**
 * @file classes/mail/variables/Variable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Variable
 * @ingroup mail_variables
 *
 * @brief A base class for email template variables
 */

namespace PKP\mail\variables;

use InvalidArgumentException;

abstract class Variable
{
    /**
     * Get descriptions of the variables provided by this class
     * @return string[]
     */
    abstract protected static function description() : array;

    /**
     * Get the value of variables supported by this class
     * @return string[]
     */
    abstract public function values(string $locale) : array;

    /**
     * Get description of all or specific variable
     * @param string|null $variableConst
     * @return string|string[]
     */
    static function getDescription(string $variableConst = null)
    {
        $description = static::description();
        if (!is_null($variableConst)) {
            if (!array_key_exists($variableConst, $description)) {
                throw new InvalidArgumentException('Template variable \'' . $variableConst . '\' doesn\'t exist in ' . static::class);
            }
            return $description[$variableConst];
        }
        return $description;
    }
}
