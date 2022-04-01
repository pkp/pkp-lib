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

abstract class Variable
{
    /**
     * Get descriptions of the variables provided by this class
     *
     * @return string[]
     */
    abstract public static function descriptions(): array;

    /**
     * Get the value of variables supported by this class
     *
     * @return string[]
     */
    abstract public function values(string $locale): array;
}
